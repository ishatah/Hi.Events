<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Operations;

use HiEvents\DomainObjects\Status\TaskStatus;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Records a go / no-go decision against a frozen snapshot of the checks.
 *
 * The system never blocks the doors. A platform that refuses to open gates because a check
 * failed produces exactly the failure offline-first exists to prevent: a door that does not
 * work, with a queue attached. Its job is to make the risk visible and owned, not to
 * override the person responsible for the event.
 *
 * So a blocking failure prevents a clean GO from being recorded without an explicit waiver
 * naming who waived what and why. Nothing is locked, and a NO_GO or an overridden GO is
 * recorded like any other decision.
 *
 * @see docs/arzo-master-plan/59-event-readiness.md
 */
class ReadinessReviewService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly TaskTemplateService $taskTemplateService,
    ) {}

    /**
     * Opens a review and freezes the current state of every check into it.
     */
    public function open(int $eventId, string $reviewPoint): int
    {
        return $this->databaseManager->transaction(function () use ($eventId, $reviewPoint): int {
            $reviewId = (int) $this->databaseManager->table('readiness_reviews')->insertGetId([
                'short_id' => 'rr_'.Str::lower(Str::random(20)),
                'event_id' => $eventId,
                'review_point' => $reviewPoint,
                'decision' => 'PENDING',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->snapshotChecks($reviewId, $eventId);

            return $reviewId;
        });
    }

    /**
     * @param  array<int, array{item_id: int, reason: string}>  $waivers
     *
     * @throws ResourceConflictException
     */
    public function decide(
        int $reviewId,
        string $decision,
        int $decidedByUserId,
        ?string $notes = null,
        array $waivers = [],
    ): void {
        if (! in_array($decision, ['GO', 'GO_WITH_RISKS', 'NO_GO'], true)) {
            throw new ResourceConflictException(__('Unknown readiness decision.'));
        }

        $this->databaseManager->transaction(function () use ($reviewId, $decision, $decidedByUserId, $notes, $waivers): void {
            $review = $this->databaseManager->table('readiness_reviews')
                ->where('id', $reviewId)
                ->lockForUpdate()
                ->first();

            if ($review === null) {
                throw new ResourceConflictException(__('That readiness review could not be found.'));
            }

            if ($review->decision !== 'PENDING') {
                throw new ResourceConflictException(
                    __('This review has already been decided. Open a new one instead.')
                );
            }

            $this->applyWaivers($reviewId, $decidedByUserId, $waivers);

            // A NO_GO needs no waivers: recording that the event is not ready is the honest
            // outcome and must never be harder than overriding.
            if ($decision !== 'NO_GO') {
                $this->guardBlockersAreResolvedOrWaived($reviewId, $decision);
            }

            $this->databaseManager->table('readiness_reviews')
                ->where('id', $reviewId)
                ->update([
                    'decision' => $decision,
                    'decided_by' => $decidedByUserId,
                    'decided_at' => now(),
                    'notes' => $notes,
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * @return array<int, object>
     */
    public function items(int $reviewId): array
    {
        return $this->databaseManager->table('readiness_items')
            ->where('readiness_review_id', $reviewId)
            ->orderByDesc('severity')
            ->get()
            ->all();
    }

    /**
     * @param  array<int, array{item_id: int, reason: string}>  $waivers
     *
     * @throws ResourceConflictException
     */
    private function applyWaivers(int $reviewId, int $decidedByUserId, array $waivers): void
    {
        foreach ($waivers as $waiver) {
            $reason = trim((string) ($waiver['reason'] ?? ''));

            if ($reason === '') {
                throw new ResourceConflictException(
                    __('A waiver must say why the risk is accepted.')
                );
            }

            $updated = $this->databaseManager->table('readiness_items')
                ->where('id', $waiver['item_id'])
                ->where('readiness_review_id', $reviewId)
                ->update([
                    'status' => 'WAIVED',
                    'waived_by' => $decidedByUserId,
                    'waiver_reason' => $reason,
                ]);

            if ($updated === 0) {
                throw new ResourceConflictException(
                    __('A waived item does not belong to this review.')
                );
            }
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function guardBlockersAreResolvedOrWaived(int $reviewId, string $decision): void
    {
        $outstanding = $this->databaseManager->table('readiness_items')
            ->where('readiness_review_id', $reviewId)
            ->where('severity', 'BLOCKING')
            ->whereIn('status', ['FAIL', 'WARN'])
            ->count();

        if ($outstanding === 0) {
            return;
        }

        throw new ResourceConflictException(
            __(':count blocking check(s) have not passed. Waive them explicitly or record a NO_GO.', [
                'count' => $outstanding,
            ])
        );
    }

    /**
     * Freezes what is true right now.
     *
     * The snapshot is the point: checks change minute to minute, and after an incident the
     * question is what was true when somebody signed the decision off.
     */
    private function snapshotChecks(int $reviewId, int $eventId): void
    {
        $rows = [];

        foreach ($this->taskTemplateService->outstandingBlockers($eventId) as $task) {
            $rows[] = $this->item(
                reviewId: $reviewId,
                title: (string) $task->title,
                severity: 'BLOCKING',
                status: (string) $task->status === TaskStatus::BLOCKED->value ? 'FAIL' : 'WARN',
                checkKey: $task->check_key,
                eventTaskId: (int) $task->id,
                detail: ['task_status' => $task->status, 'due_at' => $task->due_at],
            );
        }

        foreach ($this->automatedChecks($eventId) as $check) {
            $rows[] = $this->item(
                reviewId: $reviewId,
                title: (string) $check['title'],
                severity: (string) $check['severity'],
                status: (string) $check['status'],
                checkKey: $check['check_key'],
                detail: $check['detail'],
            );
        }

        if ($rows !== []) {
            // Every row carries the same keys. A batch insert builds one VALUES list from the
            // first row's columns, so a row with a different key set is silently truncated or
            // rejected outright.
            $this->databaseManager->table('readiness_items')->insert($rows);
        }
    }

    /**
     * @param  array<string, mixed>|null  $detail
     * @return array<string, mixed>
     */
    private function item(
        int $reviewId,
        string $title,
        string $severity,
        string $status,
        ?string $checkKey = null,
        ?int $eventTaskId = null,
        ?array $detail = null,
    ): array {
        return [
            'readiness_review_id' => $reviewId,
            'check_key' => $checkKey,
            'event_task_id' => $eventTaskId,
            'title' => $title,
            'severity' => $severity,
            'status' => $status,
            'detail' => $detail !== null ? json_encode($detail) : null,
            'evidence' => null,
            'waived_by' => null,
            'waiver_reason' => null,
            'evaluated_at' => now(),
        ];
    }

    /**
     * Checks the platform can answer for itself.
     *
     * @return array<int, array<string, mixed>>
     */
    private function automatedChecks(int $eventId): array
    {
        $checks = [];

        // Counting a grouped query directly wraps it in `select count(*) from (select * ...)`,
        // which loses the GROUP BY columns and errors. Counting the grouped rows as a
        // subquery is the one shape that works.
        $understaffedRows = $this->databaseManager->table('shifts')
            ->join('staff_positions', 'staff_positions.id', '=', 'shifts.staff_position_id')
            ->leftJoin('shift_assignments', function ($join) {
                $join->on('shift_assignments.shift_id', '=', 'shifts.id')
                    ->whereNotIn('shift_assignments.status', ['DECLINED', 'CANCELLED']);
            })
            ->where('staff_positions.event_id', $eventId)
            ->whereNull('shifts.deleted_at')
            ->groupBy('shifts.id', 'shifts.required_headcount')
            ->havingRaw('count(shift_assignments.id) < shifts.required_headcount')
            ->select('shifts.id');

        $understaffed = (int) $this->databaseManager->query()
            ->fromSub($understaffedRows, 'understaffed')
            ->count();

        $checks[] = [
            'check_key' => 'staffing.headcount',
            'title' => __('Every shift is staffed to its required headcount'),
            'severity' => 'BLOCKING',
            'status' => $understaffed === 0 ? 'PASS' : 'FAIL',
            'detail' => ['understaffed_shifts' => $understaffed],
        ];

        $openSev1 = $this->databaseManager->table('incidents')
            ->where('event_id', $eventId)
            ->where('severity', 'SEV1')
            ->whereIn('status', ['OPEN', 'ACKNOWLEDGED', 'IN_PROGRESS'])
            ->whereNull('deleted_at')
            ->count();

        $checks[] = [
            'check_key' => 'incidents.no_open_sev1',
            'title' => __('No unresolved SEV1 incidents'),
            'severity' => 'BLOCKING',
            'status' => $openSev1 === 0 ? 'PASS' : 'FAIL',
            'detail' => ['open_sev1' => $openSev1],
        ];

        $offlineDevices = $this->databaseManager->table('devices')
            ->where('event_id', $eventId)
            ->where('status', 'ACTIVE')
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', now()->subMinutes(15));
            })
            ->count();

        $checks[] = [
            'check_key' => 'devices.online',
            'title' => __('Every active device has checked in recently'),
            'severity' => 'ADVISORY',
            'status' => $offlineDevices === 0 ? 'PASS' : 'WARN',
            'detail' => ['devices_not_seen_recently' => $offlineDevices],
        ];

        return $checks;
    }
}
