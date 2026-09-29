<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge;

use HiEvents\DomainObjects\Status\BadgePrintJobStatus;
use HiEvents\DomainObjects\Status\BadgeStatus;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * The print queue.
 *
 * A print job is a retryable entity rather than a fire-and-forget call, because a jam, a
 * paper-out or a dropped connection must not lose the badge. The desk operator needs to
 * press retry, not re-enter the person.
 *
 * @see docs/arzo-master-plan/21-badge-management.md
 */
class BadgePrintJobService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function queue(
        int $badgeId,
        ?string $printerIdentifier = null,
        ?string $clientGeneratedId = null,
    ): int {
        $badge = $this->databaseManager->table('badges')->where('id', $badgeId)->first();

        if ($badge === null) {
            throw new ResourceConflictException(__('The badge could not be found.'));
        }

        $status = BadgeStatus::tryFrom((string) $badge->status);

        if ($status === null || ! $status->isUsable()) {
            throw new ResourceConflictException(
                __('A voided or replaced badge cannot be printed.')
            );
        }

        if ($clientGeneratedId !== null) {
            $existing = $this->databaseManager->table('badge_print_jobs')
                ->where('client_generated_id', $clientGeneratedId)
                ->first();

            if ($existing !== null) {
                return (int) $existing->id;
            }
        }

        try {
            return (int) $this->databaseManager->table('badge_print_jobs')->insertGetId([
                'short_id' => 'bp_'.Str::lower(Str::random(20)),
                'badge_id' => $badgeId,
                'printer_identifier' => $printerIdentifier,
                'status' => BadgePrintJobStatus::QUEUED->value,
                'attempts' => 0,
                'queued_at' => now(),
                'client_generated_id' => $clientGeneratedId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = $this->databaseManager->table('badge_print_jobs')
                ->where('client_generated_id', $clientGeneratedId)
                ->first();

            if ($existing === null) {
                throw new ResourceConflictException(__('The print job could not be queued.'));
            }

            return (int) $existing->id;
        }
    }

    /**
     * Claims the next job for a printer.
     *
     * Locked and skipped rather than queued in memory, so two print hosts polling the same
     * printer cannot both take the same job and print the badge twice.
     *
     * @return object|null the claimed job
     */
    public function claimNext(string $printerIdentifier): ?object
    {
        return $this->databaseManager->transaction(function () use ($printerIdentifier): ?object {
            $job = $this->databaseManager->table('badge_print_jobs')
                ->where('status', BadgePrintJobStatus::QUEUED->value)
                ->where(function ($query) use ($printerIdentifier) {
                    $query->where('printer_identifier', $printerIdentifier)
                        ->orWhereNull('printer_identifier');
                })
                ->orderBy('queued_at')
                // SKIP LOCKED rather than a plain FOR UPDATE: a second print host polling
                // the same printer steps over the locked row instead of blocking behind it,
                // so neither waits and neither takes the same job.
                ->lock('for update skip locked')
                ->first();

            if ($job === null) {
                return null;
            }

            $this->databaseManager->table('badge_print_jobs')
                ->where('id', $job->id)
                ->update([
                    'status' => BadgePrintJobStatus::SENT->value,
                    'printer_identifier' => $printerIdentifier,
                    'attempts' => $job->attempts + 1,
                    'sent_at' => now(),
                    'updated_at' => now(),
                ]);

            return $this->databaseManager->table('badge_print_jobs')->where('id', $job->id)->first();
        });
    }

    /**
     * @throws ResourceConflictException
     */
    public function confirm(int $printJobId, ?int $actorUserId = null): void
    {
        $job = $this->databaseManager->table('badge_print_jobs')->where('id', $printJobId)->first();

        if ($job === null) {
            throw new ResourceConflictException(__('The print job could not be found.'));
        }

        $this->databaseManager->transaction(function () use ($job, $printJobId, $actorUserId): void {
            $this->databaseManager->table('badge_print_jobs')
                ->where('id', $printJobId)
                ->update([
                    'status' => BadgePrintJobStatus::CONFIRMED->value,
                    'confirmed_at' => now(),
                    'last_error' => null,
                    'updated_at' => now(),
                ]);

            $this->databaseManager->table('badges')
                ->where('id', $job->badge_id)
                ->update([
                    'status' => BadgeStatus::PRINTED->value,
                    'printed_at' => now(),
                    'printed_by' => $actorUserId,
                    'print_count' => $this->databaseManager->raw('print_count + 1'),
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * @throws ResourceConflictException
     */
    public function fail(int $printJobId, string $error): void
    {
        $job = $this->databaseManager->table('badge_print_jobs')->where('id', $printJobId)->first();

        if ($job === null) {
            throw new ResourceConflictException(__('The print job could not be found.'));
        }

        // Back to QUEUED while attempts remain, so a jam clears itself once the printer is
        // fixed. Past the ceiling it stays FAILED and needs a human, rather than looping.
        $exhausted = (int) $job->attempts >= self::MAX_ATTEMPTS;

        $this->databaseManager->table('badge_print_jobs')
            ->where('id', $printJobId)
            ->update([
                'status' => $exhausted
                    ? BadgePrintJobStatus::FAILED->value
                    : BadgePrintJobStatus::QUEUED->value,
                'last_error' => $error,
                'updated_at' => now(),
            ]);
    }

    /**
     * @throws ResourceConflictException
     */
    public function retry(int $printJobId): void
    {
        $job = $this->databaseManager->table('badge_print_jobs')->where('id', $printJobId)->first();

        if ($job === null) {
            throw new ResourceConflictException(__('The print job could not be found.'));
        }

        if ($job->status === BadgePrintJobStatus::CONFIRMED->value) {
            throw new ResourceConflictException(__('A confirmed print job cannot be retried.'));
        }

        // A manual retry resets the attempt count: somebody has looked at the printer.
        $this->databaseManager->table('badge_print_jobs')
            ->where('id', $printJobId)
            ->update([
                'status' => BadgePrintJobStatus::QUEUED->value,
                'attempts' => 0,
                'last_error' => null,
                'queued_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Jobs that were sent to a printer and never confirmed.
     *
     * A print host that crashes mid-job leaves the row SENT forever, which looks like
     * "printing" to an operator who is actually waiting on nothing.
     *
     * @return array<int, object>
     */
    public function stalledJobs(int $olderThanSeconds = 120): array
    {
        return $this->databaseManager->table('badge_print_jobs')
            ->where('status', BadgePrintJobStatus::SENT->value)
            ->where('sent_at', '<', now()->subSeconds($olderThanSeconds))
            ->get()
            ->all();
    }
}
