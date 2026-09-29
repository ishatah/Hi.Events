<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Operations;

use Carbon\Carbon;
use HiEvents\DomainObjects\Status\IncidentSeverity;
use HiEvents\DomainObjects\Status\IncidentStatus;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Records and tracks incidents during an event.
 *
 * Every transition writes an update row. An incident log with only a current status cannot
 * answer the question a debrief actually asks — how long until somebody took ownership, and
 * who did what in between.
 *
 * @see docs/arzo-master-plan/60-incident-management.md
 */
class IncidentService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function report(
        int $eventId,
        string $title,
        string $category,
        IncidentSeverity $severity,
        ?int $reportedByUserId = null,
        ?string $description = null,
        ?int $zoneId = null,
        ?Carbon $occurredAt = null,
    ): int {
        if (trim($title) === '') {
            throw new ResourceConflictException(__('An incident needs a title.'));
        }

        return $this->databaseManager->transaction(function () use (
            $eventId,
            $title,
            $category,
            $severity,
            $reportedByUserId,
            $description,
            $zoneId,
            $occurredAt
        ): int {
            $occurredAt ??= Carbon::now();

            $incidentId = (int) $this->databaseManager->table('incidents')->insertGetId([
                'short_id' => 'in_'.Str::lower(Str::random(20)),
                'event_id' => $eventId,
                'reference' => $this->nextReference($eventId),
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'severity' => $severity->value,
                'status' => IncidentStatus::OPEN->value,
                'zone_id' => $zoneId,
                'reported_by' => $reportedByUserId,
                'occurred_at' => $occurredAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->recordUpdate(
                incidentId: $incidentId,
                authorUserId: $reportedByUserId,
                fromStatus: null,
                toStatus: IncidentStatus::OPEN,
                note: __('Reported'),
            );

            return $incidentId;
        });
    }

    /**
     * @throws ResourceConflictException
     */
    public function transition(
        int $incidentId,
        IncidentStatus $to,
        ?int $actorUserId = null,
        ?string $note = null,
        ?string $resolution = null,
    ): void {
        $this->databaseManager->transaction(function () use ($incidentId, $to, $actorUserId, $note, $resolution): void {
            $incident = $this->load($incidentId);
            $from = IncidentStatus::tryFrom((string) $incident->status);

            if ($from === null || ! $from->canTransitionTo($to)) {
                throw new ResourceConflictException(
                    __('An incident cannot go from :from to :to.', [
                        'from' => $from?->value ?? 'unknown',
                        'to' => $to->value,
                    ])
                );
            }

            if ($to === IncidentStatus::RESOLVED && ($resolution === null || trim($resolution) === '')) {
                throw new ResourceConflictException(
                    __('Resolving an incident requires a description of what was done.')
                );
            }

            $attributes = ['status' => $to->value, 'updated_at' => now()];

            // Acknowledgement is stamped once. A second acknowledgement would overwrite the
            // first, and time-to-acknowledge is the number a control room is judged on.
            if ($to === IncidentStatus::ACKNOWLEDGED && $incident->acknowledged_at === null) {
                $attributes['acknowledged_at'] = now();
            }

            if ($to === IncidentStatus::IN_PROGRESS && $incident->acknowledged_at === null) {
                $attributes['acknowledged_at'] = now();
            }

            if ($to === IncidentStatus::RESOLVED) {
                $attributes['resolved_at'] = now();
                $attributes['resolution'] = $resolution;
            }

            // Reopening clears the resolution timestamp: an incident that is open again was
            // not resolved, and leaving the stamp would understate how long it ran.
            if ($to === IncidentStatus::IN_PROGRESS && $from === IncidentStatus::RESOLVED) {
                $attributes['resolved_at'] = null;
            }

            $this->databaseManager->table('incidents')
                ->where('id', $incidentId)
                ->update($attributes);

            $this->recordUpdate(
                incidentId: $incidentId,
                authorUserId: $actorUserId,
                fromStatus: $from,
                toStatus: $to,
                note: $note,
            );
        });
    }

    public function assign(int $incidentId, ?int $assigneeUserId, ?int $actorUserId = null): void
    {
        $this->databaseManager->table('incidents')
            ->where('id', $incidentId)
            ->update(['assigned_to' => $assigneeUserId, 'updated_at' => now()]);

        $this->recordUpdate(
            incidentId: $incidentId,
            authorUserId: $actorUserId,
            fromStatus: null,
            toStatus: null,
            note: $assigneeUserId !== null ? __('Assigned') : __('Unassigned'),
        );
    }

    public function addNote(int $incidentId, ?int $authorUserId, string $note): void
    {
        $this->recordUpdate(
            incidentId: $incidentId,
            authorUserId: $authorUserId,
            fromStatus: null,
            toStatus: null,
            note: $note,
        );
    }

    /**
     * Incidents past their acknowledgement target and still unacknowledged.
     *
     * The list a control room escalates from, rather than a count of everything open.
     *
     * @return array<int, object>
     */
    public function breachingAcknowledgement(int $eventId): array
    {
        $open = $this->databaseManager->table('incidents')
            ->where('event_id', $eventId)
            ->whereNull('acknowledged_at')
            ->whereNull('deleted_at')
            ->whereIn('status', [IncidentStatus::OPEN->value])
            ->get();

        return $open->filter(function (object $incident): bool {
            $severity = IncidentSeverity::tryFrom((string) $incident->severity);

            if ($severity === null) {
                return false;
            }

            return Carbon::parse((string) $incident->occurred_at)
                ->addMinutes($severity->acknowledgementTargetMinutes())
                ->isPast();
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $eventId): array
    {
        $rows = $this->databaseManager->table('incidents')
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->selectRaw('severity, status, count(*) as total')
            ->groupBy('severity', 'status')
            ->get();

        $bySeverity = [];
        $open = 0;
        $total = 0;

        foreach (IncidentSeverity::cases() as $case) {
            $bySeverity[$case->value] = 0;
        }

        foreach ($rows as $row) {
            $bySeverity[(string) $row->severity] += (int) $row->total;
            $total += (int) $row->total;

            $status = IncidentStatus::tryFrom((string) $row->status);

            if ($status !== null && $status->isOpen()) {
                $open += (int) $row->total;
            }
        }

        return [
            'total' => $total,
            'open' => $open,
            'by_severity' => $bySeverity,
            'breaching_acknowledgement' => count($this->breachingAcknowledgement($eventId)),
        ];
    }

    /**
     * A per-event sequence, so radio traffic can say "incident 12" rather than a database id
     * that means nothing on site.
     */
    private function nextReference(int $eventId): string
    {
        $highest = $this->databaseManager->table('incidents')
            ->where('event_id', $eventId)
            ->selectRaw("max(nullif(regexp_replace(reference, '\\D', '', 'g'), '')::int) as highest")
            ->value('highest');

        return (string) (((int) $highest) + 1);
    }

    /**
     * @throws ResourceConflictException
     */
    private function load(int $incidentId): object
    {
        $incident = $this->databaseManager->table('incidents')
            ->where('id', $incidentId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if ($incident === null) {
            throw new ResourceConflictException(__('That incident could not be found.'));
        }

        return $incident;
    }

    private function recordUpdate(
        int $incidentId,
        ?int $authorUserId,
        ?IncidentStatus $fromStatus,
        ?IncidentStatus $toStatus,
        ?string $note,
    ): void {
        $this->databaseManager->table('incident_updates')->insert([
            'incident_id' => $incidentId,
            'author_user_id' => $authorUserId,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus?->value,
            'note' => $note,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }
}
