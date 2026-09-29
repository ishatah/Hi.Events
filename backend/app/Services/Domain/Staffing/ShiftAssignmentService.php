<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Staffing;

use Carbon\Carbon;
use HiEvents\DomainObjects\Status\ShiftAssignmentStatus;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Rosters people onto shifts.
 *
 * The database refuses to double-book a person through a GiST exclusion constraint rather
 * than this service checking first: a read-then-write check loses the race between two
 * schedulers, and a rota screen cannot be trusted to notice.
 *
 * @see docs/arzo-master-plan/57-manpower-and-staffing.md
 */
class ShiftAssignmentService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function assign(int $shiftId, int $personId, ?int $accreditationId = null): int
    {
        $shift = $this->databaseManager->table('shifts')
            ->where('id', $shiftId)
            ->whereNull('deleted_at')
            ->first();

        if ($shift === null) {
            throw new ResourceConflictException(__('That shift could not be found.'));
        }

        $this->guardPersonBelongsToEventAccount($personId, (int) $shift->staff_position_id);

        try {
            return (int) $this->databaseManager->table('shift_assignments')->insertGetId([
                'short_id' => 'sa_'.Str::lower(Str::random(20)),
                'shift_id' => $shiftId,
                'person_id' => $personId,
                'accreditation_id' => $accreditationId,
                'status' => ShiftAssignmentStatus::ASSIGNED->value,
                // Copied from the shift: the exclusion constraint needs the range on this row.
                'starts_at' => $shift->starts_at,
                'ends_at' => $shift->ends_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ($this->isOverlapViolation($exception)) {
                throw new ResourceConflictException(
                    __('That person is already rostered on an overlapping shift.')
                );
            }

            throw $exception;
        }
    }

    /**
     * @throws ResourceConflictException
     */
    public function transition(int $assignmentId, ShiftAssignmentStatus $to): void
    {
        $assignment = $this->load($assignmentId);
        $from = ShiftAssignmentStatus::tryFrom((string) $assignment->status);

        if ($from === null || ! $from->canTransitionTo($to)) {
            throw new ResourceConflictException(
                __('A :from shift cannot become :to.', [
                    'from' => $from?->value ?? 'unknown',
                    'to' => $to->value,
                ])
            );
        }

        $attributes = ['status' => $to->value, 'updated_at' => now()];

        if ($to === ShiftAssignmentStatus::CHECKED_IN) {
            $attributes['checked_in_at'] = now();
        }

        if ($to === ShiftAssignmentStatus::COMPLETED) {
            $attributes['checked_out_at'] = now();
        }

        $this->databaseManager->table('shift_assignments')
            ->where('id', $assignmentId)
            ->update($attributes);
    }

    /**
     * Moving a shift moves its assignments in the same transaction.
     *
     * The times live on both rows because the constraint needs the range locally, so leaving
     * the assignments behind would make the constraint enforce a schedule nobody is working.
     *
     * @throws ResourceConflictException
     */
    public function reschedule(int $shiftId, Carbon $startsAt, Carbon $endsAt): void
    {
        if ($endsAt->lte($startsAt)) {
            throw new ResourceConflictException(__('A shift must end after it starts.'));
        }

        $this->databaseManager->transaction(function () use ($shiftId, $startsAt, $endsAt): void {
            $this->databaseManager->table('shifts')
                ->where('id', $shiftId)
                ->update(['starts_at' => $startsAt, 'ends_at' => $endsAt, 'updated_at' => now()]);

            try {
                $this->databaseManager->table('shift_assignments')
                    ->where('shift_id', $shiftId)
                    ->whereNotIn('status', [
                        ShiftAssignmentStatus::DECLINED->value,
                        ShiftAssignmentStatus::CANCELLED->value,
                    ])
                    ->update(['starts_at' => $startsAt, 'ends_at' => $endsAt, 'updated_at' => now()]);
            } catch (QueryException $exception) {
                if ($this->isOverlapViolation($exception)) {
                    throw new ResourceConflictException(
                        __('Moving this shift would double-book somebody already rostered elsewhere.')
                    );
                }

                throw $exception;
            }
        });
    }

    /**
     * Shifts that do not have the headcount they need.
     *
     * The number an operations manager actually wants at a go/no-go: not how many people are
     * rostered, but where the gaps are.
     *
     * @return array<int, array{shift_id: int, position: string, required: int, filled: int}>
     */
    public function understaffedShifts(int $eventId): array
    {
        $rows = $this->databaseManager->table('shifts')
            ->join('staff_positions', 'staff_positions.id', '=', 'shifts.staff_position_id')
            ->leftJoin('shift_assignments', function ($join) {
                $join->on('shift_assignments.shift_id', '=', 'shifts.id')
                    ->whereIn('shift_assignments.status', [
                        ShiftAssignmentStatus::ASSIGNED->value,
                        ShiftAssignmentStatus::CONFIRMED->value,
                        ShiftAssignmentStatus::CHECKED_IN->value,
                        ShiftAssignmentStatus::COMPLETED->value,
                    ]);
            })
            ->where('staff_positions.event_id', $eventId)
            ->whereNull('shifts.deleted_at')
            ->whereNull('staff_positions.deleted_at')
            ->groupBy('shifts.id', 'staff_positions.name', 'shifts.required_headcount')
            ->havingRaw('count(shift_assignments.id) < shifts.required_headcount')
            ->select([
                'shifts.id as shift_id',
                'staff_positions.name as position',
                'shifts.required_headcount as required',
                $this->databaseManager->raw('count(shift_assignments.id) as filled'),
            ])
            ->get();

        return $rows->map(static fn (object $row): array => [
            'shift_id' => (int) $row->shift_id,
            'position' => (string) $row->position,
            'required' => (int) $row->required,
            'filled' => (int) $row->filled,
        ])->all();
    }

    /**
     * @throws ResourceConflictException
     */
    private function load(int $assignmentId): object
    {
        $assignment = $this->databaseManager->table('shift_assignments')
            ->where('id', $assignmentId)
            ->first();

        if ($assignment === null) {
            throw new ResourceConflictException(__('That shift assignment could not be found.'));
        }

        return $assignment;
    }

    private function isOverlapViolation(QueryException $exception): bool
    {
        // 23P01 is Postgres' exclusion_violation. Matching on the code rather than the
        // message keeps this working when the constraint is renamed or the locale changes.
        return ($exception->getCode() === '23P01')
            || str_contains($exception->getMessage(), 'shift_assignments_no_person_overlap');
    }

    /**
     * @throws ResourceConflictException
     */
    private function guardPersonBelongsToEventAccount(int $personId, int $staffPositionId): void
    {
        $accountId = $this->databaseManager->table('staff_positions')
            ->join('events', 'events.id', '=', 'staff_positions.event_id')
            ->where('staff_positions.id', $staffPositionId)
            ->value('events.account_id');

        $belongs = $this->databaseManager->table('persons')
            ->where('id', $personId)
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $belongs) {
            throw new ResourceConflictException(__('That person does not belong to this account.'));
        }
    }
}
