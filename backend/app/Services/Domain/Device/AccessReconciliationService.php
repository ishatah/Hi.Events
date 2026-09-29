<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Device;

use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Surfaces what offline operation got wrong, without rewriting the record of it.
 *
 * An access log is evidence. A device that admitted a badge revoked while it was
 * disconnected made a decision that was correct on the information it had, and the log keeps
 * saying GRANTED — the problem is recorded beside it. A system that edits its own audit
 * trail to look consistent is worse than one that reports the inconsistency.
 *
 * @see docs/arzo-master-plan/71-realtime-architecture.md
 */
class AccessReconciliationService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * Compares what a device decided offline against what the server would decide now.
     */
    public function reviewSubmittedLog(
        int $eventId,
        int $deviceId,
        string $clientGeneratedId,
        ?string $deviceDecision,
        string $serverDecision,
    ): void {
        $log = $this->databaseManager->table('access_logs')
            ->where('event_id', $eventId)
            ->where('client_generated_id', $clientGeneratedId)
            ->first();

        if ($log === null) {
            return;
        }

        // A device that granted where the server would now deny is the case that matters:
        // somebody got in on a credential that had been revoked, and a supervisor needs to
        // know which door and when.
        if ($deviceDecision !== null
            && str_starts_with($deviceDecision, 'GRANTED')
            && ! str_starts_with($serverDecision, 'GRANTED')
        ) {
            $this->record(
                eventId: $eventId,
                accessLogId: (int) $log->id,
                credentialId: $log->credential_id !== null ? (int) $log->credential_id : null,
                deviceId: $deviceId,
                findingType: 'OFFLINE_GRANT_NOW_DENIED',
                severity: 'HIGH',
                detail: __('A device admitted this credential offline; the server would now deny it (:reason).', [
                    'reason' => $serverDecision,
                ]),
                context: ['device_decision' => $deviceDecision, 'server_decision' => $serverDecision],
            );
        }

        if ($log->is_offline_replay) {
            $this->reviewCapacityViolation($eventId, $deviceId, $log);
        }
    }

    /**
     * Two devices admitting the same credential into a capacity-1 zone is a real conflict.
     *
     * Both scans stay logged and the violation is flagged — silently correcting one of them
     * would destroy the evidence of what actually happened at the door.
     */
    private function reviewCapacityViolation(int $eventId, int $deviceId, object $log): void
    {
        if ($log->zone_id === null) {
            return;
        }

        $capacity = $this->databaseManager->table('zones')
            ->where('id', $log->zone_id)
            ->value('capacity');

        if ($capacity === null) {
            return;
        }

        $inside = $this->databaseManager->table('access_logs')
            ->where('zone_id', $log->zone_id)
            ->where('event_id', $eventId)
            ->where('result', 'GRANTED')
            ->where('direction', 'ENTRY')
            ->where('occurred_at', '<=', $log->occurred_at)
            ->distinct()
            ->count('credential_id');

        if ($inside <= (int) $capacity) {
            return;
        }

        $this->record(
            eventId: $eventId,
            accessLogId: (int) $log->id,
            credentialId: $log->credential_id !== null ? (int) $log->credential_id : null,
            deviceId: $deviceId,
            findingType: 'OFFLINE_CAPACITY_EXCEEDED',
            severity: 'MEDIUM',
            detail: __('Offline admissions took this zone past its capacity of :capacity.', [
                'capacity' => (int) $capacity,
            ]),
            context: ['inside_at_scan' => $inside, 'capacity' => (int) $capacity],
        );
    }

    /**
     * A walk-in created offline that looks like somebody already on file.
     *
     * Flagged for a human to merge, never merged automatically: two people genuinely can
     * share a name, and merging the wrong records is far harder to undo than leaving a
     * duplicate.
     */
    public function flagPossibleDuplicatePerson(
        int $eventId,
        int $accessLogId,
        int $personId,
        int $possibleMatchPersonId,
    ): void {
        $this->record(
            eventId: $eventId,
            accessLogId: $accessLogId,
            credentialId: null,
            deviceId: null,
            findingType: 'POSSIBLE_DUPLICATE_PERSON',
            severity: 'LOW',
            detail: __('An offline registration may duplicate an existing person.'),
            context: ['person_id' => $personId, 'possible_match_person_id' => $possibleMatchPersonId],
        );
    }

    /**
     * @return array<int, object>
     */
    public function openFindings(int $eventId): array
    {
        return $this->databaseManager->table('access_reconciliation_findings')
            ->where('event_id', $eventId)
            ->where('status', 'OPEN')
            ->orderByRaw("CASE severity WHEN 'HIGH' THEN 1 WHEN 'MEDIUM' THEN 2 ELSE 3 END")
            ->orderByDesc('detected_at')
            ->get()
            ->all();
    }

    /**
     * @throws ResourceConflictException
     */
    public function review(int $findingId, int $reviewerUserId, string $note, string $outcome = 'REVIEWED'): void
    {
        if (trim($note) === '') {
            throw new ResourceConflictException(
                __('Closing a finding requires a note saying what was concluded.')
            );
        }

        $updated = $this->databaseManager->table('access_reconciliation_findings')
            ->where('id', $findingId)
            ->where('status', 'OPEN')
            ->update([
                'status' => $outcome,
                'reviewed_by' => $reviewerUserId,
                'reviewed_at' => now(),
                'review_note' => $note,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw new ResourceConflictException(__('That finding is not open for review.'));
        }
    }

    /**
     * @return array<string, int>
     */
    public function summary(int $eventId): array
    {
        $rows = $this->databaseManager->table('access_reconciliation_findings')
            ->where('event_id', $eventId)
            ->selectRaw('severity, status, count(*) as total')
            ->groupBy('severity', 'status')
            ->get();

        $summary = ['total' => 0, 'open' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];

        foreach ($rows as $row) {
            $summary['total'] += (int) $row->total;
            $summary[(string) $row->severity] = ($summary[(string) $row->severity] ?? 0) + (int) $row->total;

            if ((string) $row->status === 'OPEN') {
                $summary['open'] += (int) $row->total;
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function record(
        int $eventId,
        int $accessLogId,
        ?int $credentialId,
        ?int $deviceId,
        string $findingType,
        string $severity,
        string $detail,
        ?array $context = null,
    ): void {
        try {
            $this->databaseManager->table('access_reconciliation_findings')->insert([
                'short_id' => 'rf_'.Str::lower(Str::random(20)),
                'event_id' => $eventId,
                'access_log_id' => $accessLogId,
                'credential_id' => $credentialId,
                'device_id' => $deviceId,
                'finding_type' => $findingType,
                'severity' => $severity,
                'detail' => $detail,
                'context' => $context !== null ? json_encode($context) : null,
                'status' => 'OPEN',
                'detected_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already flagged. Re-running reconciliation must not pile up duplicates of the
            // same problem, or the review queue becomes unusable.
        }
    }
}
