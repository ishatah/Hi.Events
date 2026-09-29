<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Operations;

use Carbon\Carbon;
use HiEvents\DomainObjects\Status\TaskStatus;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Turns a checklist template into an event's task list.
 *
 * Template items carry an anchor and an offset rather than a date, so instantiating against
 * a particular event produces absolute times and the checklist for the third gala contains
 * everything learned at the first two.
 *
 * @see docs/arzo-master-plan/58-task-management.md
 */
class TaskTemplateService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @return int number of tasks created
     *
     * @throws ResourceConflictException
     */
    public function instantiate(int $taskTemplateId, int $eventId, ?int $assigneeUserId = null): int
    {
        $template = $this->databaseManager->table('task_templates')
            ->where('id', $taskTemplateId)
            ->whereNull('deleted_at')
            ->first();

        if ($template === null) {
            throw new ResourceConflictException(__('That task template could not be found.'));
        }

        $event = $this->databaseManager->table('events')
            ->where('id', $eventId)
            ->where('account_id', $template->account_id)
            ->whereNull('deleted_at')
            ->first();

        if ($event === null) {
            throw new ResourceConflictException(
                __('That event does not belong to the template\'s account.')
            );
        }

        $items = $this->databaseManager->table('task_template_items')
            ->where('task_template_id', $taskTemplateId)
            ->orderBy('sort_order')
            ->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $anchors = $this->anchorsFor($event);
        $rows = [];

        foreach ($items as $item) {
            // An item anchored to a point this event has no date for keeps a null due date
            // rather than being dropped: the task still needs doing, it just cannot be
            // scheduled automatically.
            $anchor = $anchors[(string) $item->anchor] ?? null;

            $rows[] = [
                'short_id' => 'tk_'.Str::lower(Str::random(20)),
                'event_id' => $eventId,
                'task_template_item_id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'due_at' => $anchor?->copy()->addMinutes((int) $item->offset_minutes),
                'assignee_user_id' => $assigneeUserId,
                'status' => TaskStatus::TODO->value,
                'is_blocking' => (bool) $item->is_blocking,
                'check_key' => $item->check_key,
                'requires_evidence' => (bool) $item->requires_evidence,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->databaseManager->table('event_tasks')->insert($rows);

        return count($rows);
    }

    /**
     * Recomputes due dates after an event moves.
     *
     * A template says "T-7 days before doors"; if the event shifts, the deadline shifts with
     * it. Leaving stale absolute dates behind would have a checklist quietly showing
     * everything overdue.
     *
     * @return int number of tasks rescheduled
     */
    public function reschedule(int $eventId): int
    {
        $event = $this->databaseManager->table('events')->where('id', $eventId)->first();

        if ($event === null) {
            return 0;
        }

        $anchors = $this->anchorsFor($event);

        $tasks = $this->databaseManager->table('event_tasks')
            ->join('task_template_items', 'task_template_items.id', '=', 'event_tasks.task_template_item_id')
            ->where('event_tasks.event_id', $eventId)
            ->whereNull('event_tasks.deleted_at')
            // A finished task keeps the deadline it was finished against; moving it would
            // rewrite the record of whether it was late.
            ->whereIn('event_tasks.status', [TaskStatus::TODO->value, TaskStatus::IN_PROGRESS->value, TaskStatus::BLOCKED->value])
            ->select(['event_tasks.id', 'task_template_items.anchor', 'task_template_items.offset_minutes'])
            ->get();

        $updated = 0;

        foreach ($tasks as $task) {
            $anchor = $anchors[(string) $task->anchor] ?? null;

            if ($anchor === null) {
                continue;
            }

            $this->databaseManager->table('event_tasks')
                ->where('id', $task->id)
                ->update([
                    'due_at' => $anchor->copy()->addMinutes((int) $task->offset_minutes),
                    'updated_at' => now(),
                ]);

            $updated++;
        }

        return $updated;
    }

    /**
     * The blocking tasks still outstanding, which is what a go/no-go review consults.
     *
     * @return array<int, object>
     */
    public function outstandingBlockers(int $eventId): array
    {
        return $this->databaseManager->table('event_tasks')
            ->where('event_id', $eventId)
            ->where('is_blocking', true)
            ->whereNotIn('status', [TaskStatus::DONE->value, TaskStatus::WAIVED->value])
            ->whereNull('deleted_at')
            ->orderBy('due_at')
            ->get()
            ->all();
    }

    /**
     * @throws ResourceConflictException
     */
    public function waive(int $taskId, int $actorUserId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new ResourceConflictException(__('A waiver reason is required.'));
        }

        $task = $this->databaseManager->table('event_tasks')->where('id', $taskId)->first();

        if ($task === null) {
            throw new ResourceConflictException(__('That task could not be found.'));
        }

        $this->databaseManager->table('event_tasks')
            ->where('id', $taskId)
            ->update([
                'status' => TaskStatus::WAIVED->value,
                'waived_reason' => $reason,
                'completed_by' => $actorUserId,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @throws ResourceConflictException
     */
    public function complete(int $taskId, int $actorUserId, ?array $evidence = null): void
    {
        $task = $this->databaseManager->table('event_tasks')->where('id', $taskId)->first();

        if ($task === null) {
            throw new ResourceConflictException(__('That task could not be found.'));
        }

        // A task that says it needs evidence and is completed without any is the checklist
        // equivalent of ticking a box nobody checked.
        if ($task->requires_evidence && ($evidence === null || $evidence === [])) {
            throw new ResourceConflictException(__('This task requires evidence to complete.'));
        }

        $this->databaseManager->table('event_tasks')
            ->where('id', $taskId)
            ->update([
                'status' => TaskStatus::DONE->value,
                'evidence' => $evidence !== null ? json_encode($evidence) : $task->evidence,
                'completed_at' => now(),
                'completed_by' => $actorUserId,
                'updated_at' => now(),
            ]);
    }

    /**
     * Absolute times for each anchor on the event timeline.
     *
     * @return array<string, Carbon|null>
     */
    private function anchorsFor(object $event): array
    {
        $start = $event->start_date !== null ? Carbon::parse((string) $event->start_date) : null;
        $end = $event->end_date !== null ? Carbon::parse((string) $event->end_date) : $start;

        return [
            'CONTRACTED' => $event->created_at !== null ? Carbon::parse((string) $event->created_at) : null,
            'BUILD_UP' => $start?->copy()->subDay(),
            'DOORS_OPEN' => $start,
            'EVENT_END' => $end,
            'BREAKDOWN_END' => $end?->copy()->addDay(),
        ];
    }
}
