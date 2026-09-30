<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Networking;

use Carbon\CarbonImmutable;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Networking\DTO\MeetingClashDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Buyer-to-exhibitor and peer meetings.
 *
 * Meetings are not sessions. A session is programme content with a room and an audience; a
 * meeting is two or three people and a table, so modelling it as a session would drag
 * capacity, registration and waitlists through a flow that has none of them.
 *
 * A clash is a warning when the meeting is requested and a hard error when it is confirmed:
 * the same asymmetry the programme uses for attendee versus speaker clashes. Somebody may well
 * hold two tentative invitations and decide between them; two confirmed meetings at the same
 * time is a promise that cannot be kept.
 *
 * @see docs/arzo-master-plan/31-networking.md
 */
class MeetingService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @param  array<int, int>  $inviteePersonIds
     *
     * @throws ResourceConflictException
     */
    public function request(
        int $eventId,
        int $requesterPersonId,
        array $inviteePersonIds,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?int $eventExhibitorId = null,
        ?int $roomId = null,
        ?string $locationLabel = null,
        ?string $note = null,
    ): array {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new ResourceConflictException(__('A meeting must end after it starts.'));
        }

        $invitees = collect($inviteePersonIds)
            ->map(static fn ($id): int => (int) $id)
            ->reject(static fn (int $id): bool => $id === $requesterPersonId)
            ->unique()
            ->values();

        if ($invitees->isEmpty()) {
            throw new ResourceConflictException(__('A meeting needs at least one other person.'));
        }

        return $this->databaseManager->transaction(function () use (
            $eventId,
            $requesterPersonId,
            $invitees,
            $startsAt,
            $endsAt,
            $eventExhibitorId,
            $roomId,
            $locationLabel,
            $note
        ): array {
            $meetingId = (int) $this->databaseManager->table('meetings')->insertGetId([
                'short_id' => 'mt_'.Str::lower(Str::random(20)),
                'event_id' => $eventId,
                'event_exhibitor_id' => $eventExhibitorId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'room_id' => $roomId,
                'location_label' => $locationLabel,
                'status' => 'REQUESTED',
                'note' => $note,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $participants = [[
                'meeting_id' => $meetingId,
                'person_id' => $requesterPersonId,
                'role' => 'REQUESTER',
                // The requester has already said yes by asking.
                'response' => 'ACCEPTED',
                'responded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]];

            foreach ($invitees as $personId) {
                $participants[] = [
                    'meeting_id' => $meetingId,
                    'person_id' => $personId,
                    'role' => 'INVITEE',
                    'response' => 'PENDING',
                    'responded_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $this->databaseManager->table('meeting_participants')->insert($participants);

            return [
                'meeting_id' => $meetingId,
                'clashes' => $this->clashesFor($eventId, $invitees->push($requesterPersonId), $startsAt, $endsAt, $meetingId)
                    ->map(static fn (MeetingClashDTO $clash): array => $clash->toArray())
                    ->all(),
            ];
        });
    }

    /**
     * @throws ResourceConflictException
     */
    public function respond(int $meetingId, int $personId, bool $accept): void
    {
        $this->databaseManager->transaction(function () use ($meetingId, $personId, $accept): void {
            $participant = $this->databaseManager->table('meeting_participants')
                ->where('meeting_id', $meetingId)
                ->where('person_id', $personId)
                ->lockForUpdate()
                ->first();

            if ($participant === null) {
                throw new ResourceConflictException(__('You were not invited to that meeting.'));
            }

            $meeting = $this->databaseManager->table('meetings')
                ->where('id', $meetingId)
                ->whereNull('deleted_at')
                ->first();

            if ($meeting === null) {
                throw new ResourceConflictException(__('That meeting could not be found.'));
            }

            if (in_array((string) $meeting->status, ['CANCELLED', 'COMPLETED', 'NO_SHOW'], true)) {
                throw new ResourceConflictException(
                    __('That meeting is :status and can no longer be answered.', [
                        'status' => Str::lower((string) $meeting->status),
                    ])
                );
            }

            $this->databaseManager->table('meeting_participants')
                ->where('id', $participant->id)
                ->update([
                    'response' => $accept ? 'ACCEPTED' : 'DECLINED',
                    'responded_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->settleMeetingStatus($meetingId, (int) $meeting->event_id);
        });
    }

    /**
     * @throws ResourceConflictException
     */
    public function cancel(int $meetingId, int $eventId): void
    {
        $updated = $this->databaseManager->table('meetings')
            ->where('id', $meetingId)
            ->where('event_id', $eventId)
            ->whereNotIn('status', ['COMPLETED', 'NO_SHOW'])
            ->whereNull('deleted_at')
            ->update(['status' => 'CANCELLED', 'updated_at' => now()]);

        if ($updated === 0) {
            throw new ResourceConflictException(
                __('That meeting could not be cancelled.')
            );
        }
    }

    /**
     * @throws ResourceConflictException
     */
    public function recordOutcome(int $meetingId, int $eventId, bool $attended): void
    {
        $updated = $this->databaseManager->table('meetings')
            ->where('id', $meetingId)
            ->where('event_id', $eventId)
            ->where('status', 'CONFIRMED')
            ->whereNull('deleted_at')
            ->update([
                'status' => $attended ? 'COMPLETED' : 'NO_SHOW',
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw new ResourceConflictException(
                __('Only a confirmed meeting can be closed out.')
            );
        }
    }

    /**
     * One person's diary for the event.
     *
     * @return Collection<int, object>
     */
    public function scheduleFor(int $eventId, int $personId): Collection
    {
        return $this->databaseManager->table('meetings')
            ->join('meeting_participants', 'meeting_participants.meeting_id', '=', 'meetings.id')
            ->leftJoin('rooms', 'rooms.id', '=', 'meetings.room_id')
            ->where('meetings.event_id', $eventId)
            ->where('meeting_participants.person_id', $personId)
            ->whereNull('meetings.deleted_at')
            ->orderBy('meetings.starts_at')
            ->select([
                'meetings.id',
                'meetings.short_id',
                'meetings.starts_at',
                'meetings.ends_at',
                'meetings.status',
                'meetings.note',
                'meetings.location_label',
                'meetings.event_exhibitor_id',
                'meeting_participants.role',
                'meeting_participants.response',
                'rooms.name as room_name',
            ])
            ->get();
    }

    /**
     * Confirms a meeting belongs to the event before anything is changed through it, because
     * the id arrives in the URL and the event is what authorization was checked on.
     *
     * @throws ResourceConflictException
     */
    public function assertBelongsToEvent(int $meetingId, int $eventId): void
    {
        $exists = $this->databaseManager->table('meetings')
            ->where('id', $meetingId)
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            throw new ResourceConflictException(__('That meeting could not be found.'));
        }
    }

    /**
     * Overlapping confirmed meetings for the same people.
     *
     * @param  Collection<int, int>  $personIds
     * @return Collection<int, MeetingClashDTO>
     */
    public function clashesFor(
        int $eventId,
        Collection $personIds,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?int $excludingMeetingId = null,
    ): Collection {
        if ($personIds->isEmpty()) {
            return collect();
        }

        return $this->databaseManager->table('meetings')
            ->join('meeting_participants', 'meeting_participants.meeting_id', '=', 'meetings.id')
            ->where('meetings.event_id', $eventId)
            ->where('meetings.status', 'CONFIRMED')
            ->whereNull('meetings.deleted_at')
            ->whereIn('meeting_participants.person_id', $personIds->unique()->values())
            ->where('meeting_participants.response', 'ACCEPTED')
            // Half-open comparison: a meeting ending exactly when the next begins is
            // back-to-back, not a clash.
            ->where('meetings.starts_at', '<', $endsAt)
            ->where('meetings.ends_at', '>', $startsAt)
            ->when(
                $excludingMeetingId !== null,
                static fn ($query) => $query->where('meetings.id', '!=', $excludingMeetingId)
            )
            ->select([
                'meeting_participants.person_id',
                'meetings.id as meeting_id',
                'meetings.starts_at',
                'meetings.ends_at',
            ])
            ->get()
            ->map(static fn (object $row): MeetingClashDTO => new MeetingClashDTO(
                personId: (int) $row->person_id,
                meetingId: (int) $row->meeting_id,
                startsAt: (string) $row->starts_at,
                endsAt: (string) $row->ends_at,
            ));
    }

    /**
     * A meeting becomes confirmed once everybody has accepted, and declined once anybody has
     * refused: a two-person meeting one side declined is not happening, and leaving it
     * REQUESTED would keep it in both diaries.
     *
     * @throws ResourceConflictException
     */
    private function settleMeetingStatus(int $meetingId, int $eventId): void
    {
        $participants = $this->databaseManager->table('meeting_participants')
            ->where('meeting_id', $meetingId)
            ->get(['person_id', 'response']);

        if ($participants->contains(static fn (object $p): bool => (string) $p->response === 'DECLINED')) {
            $this->databaseManager->table('meetings')
                ->where('id', $meetingId)
                ->update(['status' => 'DECLINED', 'updated_at' => now()]);

            return;
        }

        $allAccepted = $participants->every(
            static fn (object $p): bool => (string) $p->response === 'ACCEPTED'
        );

        if (! $allAccepted) {
            return;
        }

        $meeting = $this->databaseManager->table('meetings')->where('id', $meetingId)->first();

        $clashes = $this->clashesFor(
            $eventId,
            $participants->map(static fn (object $p): int => (int) $p->person_id),
            CarbonImmutable::parse((string) $meeting->starts_at),
            CarbonImmutable::parse((string) $meeting->ends_at),
            $meetingId,
        );

        // Hard error at confirmation, not at request. Somebody may hold two tentative
        // invitations and choose between them; two confirmed meetings at once is a promise
        // that cannot be kept.
        if ($clashes->isNotEmpty()) {
            throw new ResourceConflictException(
                __('Confirming this meeting would double-book :count person(s).', [
                    'count' => $clashes->pluck('personId')->unique()->count(),
                ])
            );
        }

        $this->databaseManager->table('meetings')
            ->where('id', $meetingId)
            ->update(['status' => 'CONFIRMED', 'updated_at' => now()]);
    }
}
