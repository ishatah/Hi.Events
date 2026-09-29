<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Exhibitor;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\LeadCaptureResolution;
use HiEvents\DomainObjects\Status\LeadStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Exhibitor\DTO\LeadCaptureResultDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Records a badge scan at a booth and resolves it to a lead.
 *
 * Capture now, resolve later. The scan is recorded whatever happens — an unknown badge, a
 * visitor from another event, somebody who never consented — because booth traffic counts
 * are useful even when no data may be transferred, and because a booth phone must not
 * silently drop a scan it cannot explain.
 *
 * Personal data moves only with a consent record. Order-level marketing opt-in is consent
 * to the organizer's own marketing, a different purpose, and cannot stand in for it.
 *
 * @see docs/arzo-master-plan/33-exhibitor-lead-capture.md
 */
class LeadCaptureService
{
    /**
     * What an exhibitor receives. Narrow on purpose: a booth does not need a date of birth
     * or an ID document, and the consent text names exactly these.
     */
    private const SHAREABLE_FIELDS = ['first_name', 'last_name', 'company', 'job_title', 'email'];

    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function capture(
        int $eventExhibitorId,
        string $identifier,
        ?int $capturedByStaffId = null,
        ?string $clientGeneratedId = null,
        ?Carbon $capturedAt = null,
        string $identifierType = 'QR',
    ): LeadCaptureResultDTO {
        $exhibitor = $this->databaseManager->table('event_exhibitors')
            ->where('id', $eventExhibitorId)
            ->whereNull('deleted_at')
            ->first();

        if ($exhibitor === null) {
            throw new ResourceConflictException(__('The exhibitor could not be found.'));
        }

        $eventId = (int) $exhibitor->event_id;
        $capturedAt ??= Carbon::now();

        if ($clientGeneratedId !== null) {
            $existing = $this->databaseManager->table('lead_captures')
                ->where('event_id', $eventId)
                ->where('client_generated_id', $clientGeneratedId)
                ->first();

            if ($existing !== null) {
                return new LeadCaptureResultDTO(
                    resolution: LeadCaptureResolution::from((string) $existing->resolution),
                    leadId: $existing->lead_id !== null ? (int) $existing->lead_id : null,
                    replayed: true,
                );
            }
        }

        $identifierHash = hash('sha256', $identifier);
        $credential = $this->resolveCredential($identifierHash);

        [$resolution, $leadId] = $this->resolve($credential, $eventId, $eventExhibitorId, $capturedAt);

        $this->recordCapture(
            eventId: $eventId,
            eventExhibitorId: $eventExhibitorId,
            capturedByStaffId: $capturedByStaffId,
            identifierHash: $identifierHash,
            identifierType: $identifierType,
            capturedAt: $capturedAt,
            clientGeneratedId: $clientGeneratedId,
            resolution: $resolution,
            leadId: $leadId,
        );

        return new LeadCaptureResultDTO(resolution: $resolution, leadId: $leadId);
    }

    /**
     * @return array{0: LeadCaptureResolution, 1: int|null}
     */
    private function resolve(
        ?object $credential,
        int $eventId,
        int $eventExhibitorId,
        Carbon $capturedAt,
    ): array {
        if ($credential === null) {
            return [LeadCaptureResolution::UNKNOWN_IDENTIFIER, null];
        }

        // A badge from a different event at this event's booth. Recorded rather than
        // rejected, because it is a real thing that happens at co-located shows.
        if ((int) $credential->event_id !== $eventId) {
            return [LeadCaptureResolution::OTHER_EVENT, null];
        }

        if ($credential->person_id === null) {
            return [LeadCaptureResolution::UNKNOWN_IDENTIFIER, null];
        }

        $personId = (int) $credential->person_id;

        if (! $this->hasConsent($eventId, $personId, $eventExhibitorId)) {
            return [LeadCaptureResolution::NO_CONSENT, null];
        }

        return [
            LeadCaptureResolution::RESOLVED,
            $this->upsertLead($eventId, $eventExhibitorId, $personId, $capturedAt),
        ];
    }

    /**
     * A rescan appends a capture and bumps the count rather than creating a second lead.
     */
    private function upsertLead(
        int $eventId,
        int $eventExhibitorId,
        int $personId,
        Carbon $capturedAt,
    ): int {
        $existing = $this->databaseManager->table('leads')
            ->where('event_exhibitor_id', $eventExhibitorId)
            ->where('person_id', $personId)
            ->whereNull('deleted_at')
            ->first();

        if ($existing !== null) {
            $this->databaseManager->table('leads')
                ->where('id', $existing->id)
                ->update([
                    'last_captured_at' => $capturedAt,
                    'capture_count' => $this->databaseManager->raw('capture_count + 1'),
                    'updated_at' => now(),
                ]);

            return (int) $existing->id;
        }

        try {
            return (int) $this->databaseManager->table('leads')->insertGetId([
                'short_id' => 'ld_'.Str::lower(Str::random(20)),
                'event_id' => $eventId,
                'event_exhibitor_id' => $eventExhibitorId,
                'person_id' => $personId,
                // Snapshotted at capture time. The exhibitor's view reads this rather than
                // the live person row, so what they hold is provable later and a subsequent
                // profile edit does not rewrite history.
                'shared_fields' => json_encode($this->snapshotPerson($personId)),
                'first_captured_at' => $capturedAt,
                'last_captured_at' => $capturedAt,
                'capture_count' => 1,
                'status' => LeadStatus::NEW->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two booth phones scanning the same visitor at once. The row that won is the
            // one we would have written.
            return (int) $this->databaseManager->table('leads')
                ->where('event_exhibitor_id', $eventExhibitorId)
                ->where('person_id', $personId)
                ->whereNull('deleted_at')
                ->value('id');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotPerson(int $personId): array
    {
        $person = $this->databaseManager->table('persons')
            ->where('id', $personId)
            ->first();

        if ($person === null) {
            return [];
        }

        $snapshot = [];

        foreach (self::SHAREABLE_FIELDS as $field) {
            $snapshot[$field] = $person->{$field} ?? null;
        }

        return $snapshot;
    }

    /**
     * Consent may be given for all exhibitors at an event or for one in particular. A
     * general consent covers a specific booth; a consent for another booth does not.
     */
    private function hasConsent(int $eventId, int $personId, int $eventExhibitorId): bool
    {
        return $this->databaseManager->table('lead_consents')
            ->where('event_id', $eventId)
            ->where('person_id', $personId)
            ->whereNull('withdrawn_at')
            ->where(function ($query) use ($eventExhibitorId) {
                $query->whereNull('event_exhibitor_id')
                    ->orWhere('event_exhibitor_id', $eventExhibitorId);
            })
            ->exists();
    }

    private function resolveCredential(string $identifierHash): ?object
    {
        return $this->databaseManager->table('credentials')
            ->where('identifier_hash', $identifierHash)
            ->first();
    }

    private function recordCapture(
        int $eventId,
        int $eventExhibitorId,
        ?int $capturedByStaffId,
        string $identifierHash,
        string $identifierType,
        Carbon $capturedAt,
        ?string $clientGeneratedId,
        LeadCaptureResolution $resolution,
        ?int $leadId,
    ): void {
        try {
            $this->databaseManager->table('lead_captures')->insert([
                'event_id' => $eventId,
                'event_exhibitor_id' => $eventExhibitorId,
                'captured_by_exhibitor_staff_id' => $capturedByStaffId,
                'identifier_hash' => $identifierHash,
                'identifier_type' => $identifierType,
                'captured_at' => $capturedAt,
                'recorded_at' => now(),
                'client_generated_id' => $clientGeneratedId,
                'resolution' => $resolution->value,
                'lead_id' => $leadId,
                'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent replay of the same queued capture. Already recorded.
        }
    }

    /**
     * @return array<string, int>
     */
    public function captureStats(int $eventExhibitorId): array
    {
        $rows = $this->databaseManager->table('lead_captures')
            ->where('event_exhibitor_id', $eventExhibitorId)
            ->selectRaw('resolution, count(*) as total')
            ->groupBy('resolution')
            ->get();

        $stats = ['total' => 0];

        foreach (LeadCaptureResolution::cases() as $case) {
            $stats[$case->value] = 0;
        }

        foreach ($rows as $row) {
            $stats[(string) $row->resolution] = (int) $row->total;
            $stats['total'] += (int) $row->total;
        }

        return $stats;
    }
}
