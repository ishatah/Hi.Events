<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Session;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\AccessDirection;
use HiEvents\DomainObjects\Status\SessionRegistrationStatus;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Records attendance at a session.
 *
 * Uses the same ENTRY/EXIT vocabulary as access_logs, so a room door and a session scanner
 * describe the same act the same way. Attendance is a log rather than a flag: somebody who
 * steps out and returns produces two entries, which is what makes dwell time answerable.
 *
 * @see docs/arzo-master-plan/27-sessions-tracks.md
 */
class SessionAttendanceService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function record(
        int $sessionId,
        int $attendeeId,
        AccessDirection $direction = AccessDirection::ENTRY,
        ?int $accessPointId = null,
        ?int $recordedByUserId = null,
        ?string $clientGeneratedId = null,
        ?Carbon $scannedAt = null,
        string $source = 'SCAN',
    ): int {
        $session = $this->databaseManager->table('sessions')
            ->where('id', $sessionId)
            ->whereNull('deleted_at')
            ->first();

        if ($session === null) {
            throw new ResourceConflictException(__('The session could not be found.'));
        }

        if (! $session->check_in_enabled) {
            throw new ResourceConflictException(__('Check-in is not enabled for this session.'));
        }

        $this->guardAttendeeBelongsToEvent($attendeeId, (int) $session->event_id);

        if ($session->requires_registration && ! $this->isRegistered($sessionId, $attendeeId)) {
            throw new ResourceConflictException(__('That attendee is not registered for this session.'));
        }

        if ($clientGeneratedId !== null) {
            $existing = $this->databaseManager->table('session_attendance')
                ->where('client_generated_id', $clientGeneratedId)
                ->where('session_id', $sessionId)
                ->first();

            if ($existing !== null) {
                return (int) $existing->id;
            }
        }

        try {
            return (int) $this->databaseManager->table('session_attendance')->insertGetId([
                'short_id' => 'sa_'.Str::lower(Str::random(20)),
                'session_id' => $sessionId,
                'attendee_id' => $attendeeId,
                'scanned_at' => $scannedAt ?? Carbon::now(),
                'direction' => $direction->value,
                'access_point_id' => $accessPointId,
                'recorded_by' => $recordedByUserId,
                'source' => $source,
                'client_generated_id' => $clientGeneratedId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent replay of the same queued scan. The row it collided with is the
            // one we would have written, so return that rather than failing the scanner.
            $existing = $this->databaseManager->table('session_attendance')
                ->where('client_generated_id', $clientGeneratedId)
                ->where('session_id', $sessionId)
                ->first();

            if ($existing === null) {
                throw new ResourceConflictException(__('The attendance could not be recorded.'));
            }

            return (int) $existing->id;
        }
    }

    /**
     * Distinct attendees who entered, which is the number an organizer means by
     * "how many came". Re-entries do not inflate it.
     */
    public function attendedCount(int $sessionId): int
    {
        return $this->databaseManager->table('session_attendance')
            ->where('session_id', $sessionId)
            ->where('direction', AccessDirection::ENTRY->value)
            ->distinct()
            ->count('attendee_id');
    }

    /**
     * Registered attendees who never scanned in. The gap organizers act on when deciding
     * whether to keep running a session.
     */
    public function noShowCount(int $sessionId): int
    {
        $registered = $this->databaseManager->table('session_registrations')
            ->where('session_id', $sessionId)
            ->where('status', SessionRegistrationStatus::REGISTERED->value)
            ->whereNull('deleted_at')
            ->pluck('attendee_id');

        if ($registered->isEmpty()) {
            return 0;
        }

        $attended = $this->databaseManager->table('session_attendance')
            ->where('session_id', $sessionId)
            ->where('direction', AccessDirection::ENTRY->value)
            ->whereIn('attendee_id', $registered)
            ->distinct()
            ->pluck('attendee_id');

        return $registered->count() - $attended->count();
    }

    private function isRegistered(int $sessionId, int $attendeeId): bool
    {
        return $this->databaseManager->table('session_registrations')
            ->where('session_id', $sessionId)
            ->where('attendee_id', $attendeeId)
            ->where('status', SessionRegistrationStatus::REGISTERED->value)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * @throws ResourceConflictException
     */
    private function guardAttendeeBelongsToEvent(int $attendeeId, int $eventId): void
    {
        $belongs = $this->databaseManager->table('attendees')
            ->where('id', $attendeeId)
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $belongs) {
            throw new ResourceConflictException(__('That attendee does not belong to this event.'));
        }
    }
}
