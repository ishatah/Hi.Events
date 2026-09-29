<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Session;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * Finds sessions on an attendee's agenda that overlap in time.
 *
 * Reported rather than prevented: a delegate may legitimately register for overlapping
 * sessions intending to choose later, and refusing the second registration would be
 * presumptuous. The agenda surfaces the clash instead.
 *
 * @see docs/arzo-master-plan/27-sessions-tracks.md
 */
class SessionConflictService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @return Collection<int, array{session_id: int, conflicts_with: int, title: string, conflicting_title: string}>
     */
    public function forAttendee(int $eventId, int $attendeeId): Collection
    {
        $sessions = $this->databaseManager->table('sessions')
            ->join('session_registrations', 'session_registrations.session_id', '=', 'sessions.id')
            ->where('sessions.event_id', $eventId)
            ->where('session_registrations.attendee_id', $attendeeId)
            ->where('session_registrations.status', 'REGISTERED')
            ->whereNull('session_registrations.deleted_at')
            ->whereNull('sessions.deleted_at')
            ->orderBy('sessions.starts_at')
            ->select(['sessions.id', 'sessions.title', 'sessions.starts_at', 'sessions.ends_at'])
            ->get();

        $conflicts = collect();

        foreach ($sessions as $index => $session) {
            foreach ($sessions->slice($index + 1) as $other) {
                // Sorted by start, so once a later session starts at or after this one ends
                // no further session can overlap it.
                if ($other->starts_at >= $session->ends_at) {
                    break;
                }

                $conflicts->push([
                    'session_id' => (int) $session->id,
                    'conflicts_with' => (int) $other->id,
                    'title' => (string) $session->title,
                    'conflicting_title' => (string) $other->title,
                ]);
            }
        }

        return $conflicts;
    }
}
