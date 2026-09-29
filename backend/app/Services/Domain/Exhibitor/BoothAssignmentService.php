<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Exhibitor;

use Carbon\Carbon;
use HiEvents\DomainObjects\Status\BoothAssignmentStatus;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Allocates booths to exhibitors for one event.
 *
 * Allocation lives here rather than as a status on the booth, because a booth is a place
 * that outlives any single event while an allocation belongs to one edition. The partial
 * unique index is the real guarantee — two sales people cannot sell the same booth twice
 * even if they click at the same moment.
 *
 * @see docs/arzo-master-plan/35-booth-management.md
 */
class BoothAssignmentService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function hold(
        int $eventId,
        int $boothId,
        ?int $eventExhibitorId,
        Carbon $until,
        ?int $actorUserId = null,
    ): int {
        return $this->create(
            eventId: $eventId,
            boothId: $boothId,
            eventExhibitorId: $eventExhibitorId,
            status: BoothAssignmentStatus::HELD,
            heldUntil: $until,
            actorUserId: $actorUserId,
        );
    }

    /**
     * @throws ResourceConflictException
     */
    public function assign(
        int $eventId,
        int $boothId,
        int $eventExhibitorId,
        ?int $actorUserId = null,
        string $role = 'PRIMARY',
    ): int {
        return $this->create(
            eventId: $eventId,
            boothId: $boothId,
            eventExhibitorId: $eventExhibitorId,
            status: BoothAssignmentStatus::ASSIGNED,
            heldUntil: null,
            actorUserId: $actorUserId,
            role: $role,
        );
    }

    /**
     * Converts a hold into a firm assignment.
     *
     * @throws ResourceConflictException
     */
    public function confirm(int $assignmentId, ?int $actorUserId = null): void
    {
        $assignment = $this->load($assignmentId);

        if ((string) $assignment->status !== BoothAssignmentStatus::HELD->value) {
            throw new ResourceConflictException(__('Only a held booth can be confirmed.'));
        }

        if ($assignment->event_exhibitor_id === null) {
            throw new ResourceConflictException(
                __('A booth cannot be confirmed without an exhibitor.')
            );
        }

        $this->databaseManager->table('booth_assignments')
            ->where('id', $assignmentId)
            ->update([
                'status' => BoothAssignmentStatus::ASSIGNED->value,
                'held_until' => null,
                'assigned_at' => now(),
                'assigned_by' => $actorUserId,
                'updated_at' => now(),
            ]);
    }

    /**
     * @throws ResourceConflictException
     */
    public function markBuilt(int $assignmentId): void
    {
        $assignment = $this->load($assignmentId);

        if ((string) $assignment->status !== BoothAssignmentStatus::ASSIGNED->value) {
            throw new ResourceConflictException(
                __('Only an assigned booth can be marked as built.')
            );
        }

        $this->databaseManager->table('booth_assignments')
            ->where('id', $assignmentId)
            ->update([
                'status' => BoothAssignmentStatus::BUILT->value,
                'updated_at' => now(),
            ]);
    }

    /**
     * @throws ResourceConflictException
     */
    public function release(int $assignmentId, string $reason): void
    {
        $assignment = $this->load($assignmentId);

        if ((string) $assignment->status === BoothAssignmentStatus::RELEASED->value) {
            throw new ResourceConflictException(__('That booth is already released.'));
        }

        $this->databaseManager->table('booth_assignments')
            ->where('id', $assignmentId)
            ->update([
                'status' => BoothAssignmentStatus::RELEASED->value,
                'released_at' => now(),
                'release_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    /**
     * Releases holds whose deadline has passed.
     *
     * A hold that never expires is a booth quietly off the market, which is how an
     * exhibition ends up looking sold out while half its stands are empty.
     *
     * @return int number released
     */
    public function releaseExpiredHolds(): int
    {
        $expired = $this->databaseManager->table('booth_assignments')
            ->where('status', BoothAssignmentStatus::HELD->value)
            ->whereNotNull('held_until')
            ->where('held_until', '<', now())
            ->pluck('id');

        if ($expired->isEmpty()) {
            return 0;
        }

        $this->databaseManager->table('booth_assignments')
            ->whereIn('id', $expired)
            ->update([
                'status' => BoothAssignmentStatus::RELEASED->value,
                'released_at' => now(),
                'release_reason' => __('Hold expired'),
                'updated_at' => now(),
            ]);

        return $expired->count();
    }

    /**
     * @throws ResourceConflictException
     */
    private function create(
        int $eventId,
        int $boothId,
        ?int $eventExhibitorId,
        BoothAssignmentStatus $status,
        ?Carbon $heldUntil,
        ?int $actorUserId,
        string $role = 'PRIMARY',
    ): int {
        $this->guardBoothServesEvent($boothId, $eventId);

        if ($eventExhibitorId !== null) {
            $this->guardExhibitorBelongsToEvent($eventExhibitorId, $eventId);
        }

        try {
            return (int) $this->databaseManager->table('booth_assignments')->insertGetId([
                'short_id' => 'ba_'.Str::lower(Str::random(20)),
                'event_id' => $eventId,
                'booth_id' => $boothId,
                'event_exhibitor_id' => $eventExhibitorId,
                'role' => $role,
                'status' => $status->value,
                'held_until' => $heldUntil,
                'assigned_at' => $status === BoothAssignmentStatus::ASSIGNED ? now() : null,
                'assigned_by' => $status === BoothAssignmentStatus::ASSIGNED ? $actorUserId : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The index caught a booth already spoken for. Reporting it as a conflict is
            // more useful than a 500, and it is the only race-proof answer.
            throw new ResourceConflictException(
                __('That booth is already held or assigned for this event.')
            );
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function load(int $assignmentId): object
    {
        $assignment = $this->databaseManager->table('booth_assignments')
            ->where('id', $assignmentId)
            ->first();

        if ($assignment === null) {
            throw new ResourceConflictException(__('That booth assignment could not be found.'));
        }

        return $assignment;
    }

    /**
     * A booth either belongs to this event's own layout or is a permanent booth at a venue
     * the event uses. Anything else is another event's stand.
     *
     * @throws ResourceConflictException
     */
    private function guardBoothServesEvent(int $boothId, int $eventId): void
    {
        $booth = $this->databaseManager->table('booths')
            ->where('id', $boothId)
            ->whereNull('deleted_at')
            ->first();

        if ($booth === null) {
            throw new ResourceConflictException(__('That booth could not be found.'));
        }

        if ($booth->event_id !== null) {
            if ((int) $booth->event_id !== $eventId) {
                throw new ResourceConflictException(
                    __('That booth belongs to another event.')
                );
            }

            return;
        }

        $venueServesEvent = $this->databaseManager->table('event_venues')
            ->where('event_id', $eventId)
            ->where('venue_id', $booth->venue_id)
            ->exists();

        if (! $venueServesEvent) {
            throw new ResourceConflictException(
                __('That booth is at a venue this event does not use.')
            );
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function guardExhibitorBelongsToEvent(int $eventExhibitorId, int $eventId): void
    {
        $belongs = $this->databaseManager->table('event_exhibitors')
            ->where('id', $eventExhibitorId)
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $belongs) {
            throw new ResourceConflictException(
                __('That exhibitor is not registered for this event.')
            );
        }
    }
}
