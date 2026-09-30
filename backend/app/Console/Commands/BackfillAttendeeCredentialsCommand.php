<?php

declare(strict_types=1);

namespace HiEvents\Console\Commands;

use HiEvents\DomainObjects\Status\CredentialStatus;
use HiEvents\Services\Domain\Access\GrantMaterializationService;
use HiEvents\Services\Domain\Credential\CredentialIdentifierService;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gives every existing non-cancelled attendee a credential.
 *
 * Two passes on purpose. Materialising grants per credential at the < 500 ms budget the
 * scan path targets would take hours over a hundred thousand rows, so credentials are
 * written first in bulk and grants follow in batches.
 *
 * Idempotent: an attendee who already holds a live credential is skipped, so the command
 * can be re-run after a partial failure without issuing duplicates.
 *
 * @see docs/arzo-master-plan/122-migration-plan.md D2
 */
class BackfillAttendeeCredentialsCommand extends Command
{
    protected $signature = 'credentials:backfill-attendees
        {--event= : Limit to a single event id}
        {--chunk=500 : Rows per chunk}
        {--dry-run : Report what would change without writing}
        {--skip-grants : Issue credentials only, leaving the grant pass for a later run}';

    protected $description = 'Issue a credential for every non-cancelled attendee that lacks one';

    public function handle(
        DatabaseManager $databaseManager,
        GrantMaterializationService $grantMaterializationService,
        CredentialIdentifierService $identifierService,
    ): int {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $eventId = $this->option('event') !== null ? (int) $this->option('event') : null;

        $issued = 0;
        $skipped = 0;
        $failed = 0;

        $query = $databaseManager->table('attendees')
            ->whereNull('attendees.deleted_at')
            ->where('attendees.status', '!=', 'CANCELLED')
            ->whereNotExists(function ($sub) {
                $sub->select($sub->raw(1))
                    ->from('credentials')
                    ->whereColumn('credentials.attendee_id', 'attendees.id')
                    ->whereNotIn('credentials.status', [
                        CredentialStatus::REVOKED->value,
                        CredentialStatus::EXPIRED->value,
                    ]);
            })
            ->select(['attendees.id', 'attendees.event_id', 'attendees.product_id']);

        if ($eventId !== null) {
            $query->where('attendees.event_id', $eventId);
        }

        $total = (clone $query)->count();

        $this->info(sprintf('%d attendee(s) without a credential.', $total));

        if ($total === 0) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry run: nothing written.');

            return self::SUCCESS;
        }

        $created = [];

        // chunkById rather than chunk: the query filters on the absence of a credential,
        // and writing credentials as we go shifts the result set under a plain offset
        // pager, which silently skips rows.
        $query->orderBy('attendees.id')->chunkById($chunkSize, function ($attendees) use (
            $databaseManager,
            &$issued,
            &$skipped,
            &$failed,
            &$created
        ): void {
            $rows = [];
            $now = now();

            foreach ($attendees as $attendee) {
                $identifier = $identifierService->generate();

                $rows[] = [
                    'short_id' => 'cr_'.Str::lower(Str::random(20)),
                    'event_id' => $attendee->event_id,
                    'attendee_id' => $attendee->id,
                    'credential_type' => 'ATTENDEE',
                    'status' => CredentialStatus::ACTIVE->value,
                    'identifier' => $identifier,
                    'identifier_hash' => $identifierService->hash($identifier),
                    'issued_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows === []) {
                return;
            }

            try {
                $databaseManager->table('credentials')->insert($rows);
                $issued += count($rows);

                foreach ($attendees as $attendee) {
                    $created[] = [
                        'event_id' => (int) $attendee->event_id,
                        'product_id' => $attendee->product_id !== null ? (int) $attendee->product_id : null,
                        'attendee_id' => (int) $attendee->id,
                    ];
                }
            } catch (Throwable $exception) {
                $failed += count($rows);
                $this->error(sprintf('Chunk failed: %s', $exception->getMessage()));
            }
        }, 'attendees.id', 'id');

        $this->info(sprintf('Issued %d credential(s). Skipped %d. Failed %d.', $issued, $skipped, $failed));

        if ($this->option('skip-grants')) {
            $this->warn('Grants not materialised. Re-run without --skip-grants to complete.');

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        $granted = $this->materialiseGrants($databaseManager, $grantMaterializationService, $created);

        $this->info(sprintf('Materialised grants for %d credential(s).', $granted));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<int, array{event_id: int, product_id: int|null, attendee_id: int}>  $created
     */
    private function materialiseGrants(
        DatabaseManager $databaseManager,
        GrantMaterializationService $grantMaterializationService,
        array $created,
    ): int {
        $granted = 0;

        foreach (array_chunk($created, 200) as $batch) {
            $attendeeIds = array_column($batch, 'attendee_id');

            $credentialIds = $databaseManager->table('credentials')
                ->whereIn('attendee_id', $attendeeIds)
                ->where('status', CredentialStatus::ACTIVE->value)
                ->pluck('id', 'attendee_id');

            foreach ($batch as $row) {
                $credentialId = $credentialIds[$row['attendee_id']] ?? null;

                if ($credentialId === null) {
                    continue;
                }

                try {
                    $grantMaterializationService->materialize(
                        credentialId: (int) $credentialId,
                        eventId: $row['event_id'],
                        subjects: $row['product_id'] !== null ? ['PRODUCT' => $row['product_id']] : [],
                    );

                    $granted++;
                } catch (Throwable $exception) {
                    $this->error(sprintf(
                        'Grant materialisation failed for credential %d: %s',
                        $credentialId,
                        $exception->getMessage()
                    ));
                }
            }
        }

        return $granted;
    }
}
