<?php

namespace Tests\Feature\Services\Domain\Operations;

use Carbon\Carbon;
use HiEvents\DomainObjects\Status\IncidentSeverity;
use HiEvents\DomainObjects\Status\IncidentStatus;
use HiEvents\DomainObjects\Status\ShiftAssignmentStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Operations\IncidentService;
use HiEvents\Services\Domain\Operations\ReadinessReviewService;
use HiEvents\Services\Domain\Operations\TaskTemplateService;
use HiEvents\Services\Domain\Staffing\ShiftAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsSubsystemTest extends TestCase
{
    use DatabaseTransactions;

    private ShiftAssignmentService $shifts;

    private TaskTemplateService $tasks;

    private IncidentService $incidents;

    private ReadinessReviewService $readiness;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $positionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shifts = app(ShiftAssignmentService::class);
        $this->tasks = app(TaskTemplateService::class);
        $this->incidents = app(IncidentService::class);
        $this->readiness = app(ReadinessReviewService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent();
        $this->positionId = $this->makePosition();
    }

    // ---------------------------------------------------------------- staffing

    public function test_a_person_can_be_rostered_onto_a_shift(): void
    {
        $shiftId = $this->makeShift('2026-11-01 09:00:00+00', '2026-11-01 17:00:00+00');

        $assignmentId = $this->shifts->assign($shiftId, $this->makePerson());

        $this->assertSame(
            ShiftAssignmentStatus::ASSIGNED->value,
            DB::table('shift_assignments')->where('id', $assignmentId)->value('status')
        );
    }

    public function test_a_person_cannot_be_double_booked(): void
    {
        $personId = $this->makePerson();
        $morning = $this->makeShift('2026-11-01 09:00:00+00', '2026-11-01 17:00:00+00');
        $overlapping = $this->makeShift('2026-11-01 13:00:00+00', '2026-11-01 21:00:00+00');

        $this->shifts->assign($morning, $personId);

        $this->expectExceptionMessageMatches('/already rostered on an overlapping shift/');
        $this->shifts->assign($overlapping, $personId);
    }

    public function test_back_to_back_shifts_are_allowed(): void
    {
        $personId = $this->makePerson();
        $first = $this->makeShift('2026-11-01 09:00:00+00', '2026-11-01 17:00:00+00');
        $second = $this->makeShift('2026-11-01 17:00:00+00', '2026-11-02 01:00:00+00');

        $this->shifts->assign($first, $personId);

        $this->assertGreaterThan(
            0,
            $this->shifts->assign($second, $personId),
            'A shift starting when another ends is not a clash.'
        );
    }

    public function test_declining_frees_the_person_for_an_overlapping_shift(): void
    {
        $personId = $this->makePerson();
        $first = $this->makeShift('2026-11-01 09:00:00+00', '2026-11-01 17:00:00+00');
        $overlapping = $this->makeShift('2026-11-01 13:00:00+00', '2026-11-01 21:00:00+00');

        $assignmentId = $this->shifts->assign($first, $personId);
        $this->shifts->transition($assignmentId, ShiftAssignmentStatus::DECLINED);

        $this->assertGreaterThan(0, $this->shifts->assign($overlapping, $personId));
    }

    public function test_a_shift_assignment_follows_its_status_machine(): void
    {
        $shiftId = $this->makeShift('2026-11-01 09:00:00+00', '2026-11-01 17:00:00+00');
        $assignmentId = $this->shifts->assign($shiftId, $this->makePerson());

        $this->shifts->transition($assignmentId, ShiftAssignmentStatus::CONFIRMED);
        $this->shifts->transition($assignmentId, ShiftAssignmentStatus::CHECKED_IN);

        $row = DB::table('shift_assignments')->where('id', $assignmentId)->first();
        $this->assertNotNull($row->checked_in_at);

        $this->shifts->transition($assignmentId, ShiftAssignmentStatus::COMPLETED);
        $this->assertNotNull(
            DB::table('shift_assignments')->where('id', $assignmentId)->value('checked_out_at')
        );
    }

    public function test_an_invalid_shift_transition_is_refused(): void
    {
        $shiftId = $this->makeShift('2026-11-01 09:00:00+00', '2026-11-01 17:00:00+00');
        $assignmentId = $this->shifts->assign($shiftId, $this->makePerson());

        $this->shifts->transition($assignmentId, ShiftAssignmentStatus::DECLINED);

        $this->expectExceptionMessageMatches('/cannot become/');
        $this->shifts->transition($assignmentId, ShiftAssignmentStatus::CHECKED_IN);
    }

    public function test_rescheduling_a_shift_moves_its_assignments(): void
    {
        $shiftId = $this->makeShift('2026-11-01 09:00:00+00', '2026-11-01 17:00:00+00');
        $assignmentId = $this->shifts->assign($shiftId, $this->makePerson());

        $this->shifts->reschedule(
            $shiftId,
            Carbon::parse('2026-11-02 09:00:00+00'),
            Carbon::parse('2026-11-02 17:00:00+00')
        );

        $row = DB::table('shift_assignments')->where('id', $assignmentId)->first();

        $this->assertStringContainsString(
            '2026-11-02',
            (string) $row->starts_at,
            'The times live on both rows because the constraint needs the range locally.'
        );
    }

    public function test_rescheduling_into_a_clash_is_refused(): void
    {
        $personId = $this->makePerson();
        $first = $this->makeShift('2026-11-01 09:00:00+00', '2026-11-01 12:00:00+00');
        $second = $this->makeShift('2026-11-03 09:00:00+00', '2026-11-03 12:00:00+00');

        $this->shifts->assign($first, $personId);
        $this->shifts->assign($second, $personId);

        $this->expectExceptionMessageMatches('/would double-book/');
        $this->shifts->reschedule(
            $second,
            Carbon::parse('2026-11-01 10:00:00+00'),
            Carbon::parse('2026-11-01 14:00:00+00')
        );
    }

    public function test_a_shift_must_end_after_it_starts(): void
    {
        $shiftId = $this->makeShift('2026-11-01 09:00:00+00', '2026-11-01 17:00:00+00');

        $this->expectExceptionMessageMatches('/must end after it starts/');
        $this->shifts->reschedule(
            $shiftId,
            Carbon::parse('2026-11-01 17:00:00+00'),
            Carbon::parse('2026-11-01 09:00:00+00')
        );
    }

    public function test_understaffed_shifts_are_reported(): void
    {
        $needsTwo = $this->makeShift('2026-11-01 09:00:00+00', '2026-11-01 17:00:00+00', headcount: 2);
        $this->shifts->assign($needsTwo, $this->makePerson());

        $gaps = $this->shifts->understaffedShifts($this->eventId);

        $this->assertCount(1, $gaps);
        $this->assertSame(2, $gaps[0]['required']);
        $this->assertSame(1, $gaps[0]['filled']);
    }

    public function test_a_fully_staffed_shift_is_not_reported(): void
    {
        $shiftId = $this->makeShift('2026-11-01 09:00:00+00', '2026-11-01 17:00:00+00', headcount: 1);
        $this->shifts->assign($shiftId, $this->makePerson());

        $this->assertSame([], $this->shifts->understaffedShifts($this->eventId));
    }

    public function test_a_person_from_another_account_cannot_be_rostered(): void
    {
        $stranger = User::factory()->withAccount()->create();
        $foreignPerson = $this->makePerson((int) $stranger->accounts()->first()->id);
        $shiftId = $this->makeShift('2026-11-01 09:00:00+00', '2026-11-01 17:00:00+00');

        $this->expectExceptionMessageMatches('/does not belong to this account/');
        $this->shifts->assign($shiftId, $foreignPerson);
    }

    // ---------------------------------------------------------------- tasks

    public function test_a_template_instantiates_with_absolute_due_dates(): void
    {
        $templateId = $this->makeTemplate([
            ['title' => 'Confirm AV', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => -10080, 'is_blocking' => true],
            ['title' => 'Brief stewards', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => -120, 'is_blocking' => false],
        ]);

        $created = $this->tasks->instantiate($templateId, $this->eventId);

        $this->assertSame(2, $created);

        $task = DB::table('event_tasks')->where('title', 'Brief stewards')->first();
        $doorsOpen = Carbon::parse((string) DB::table('events')->where('id', $this->eventId)->value('start_date'));

        $this->assertSame(
            $doorsOpen->copy()->subMinutes(120)->format('Y-m-d H:i'),
            Carbon::parse((string) $task->due_at)->format('Y-m-d H:i'),
            'A relative template offset must resolve against the event timeline.'
        );
    }

    public function test_moving_the_event_moves_the_deadlines(): void
    {
        $templateId = $this->makeTemplate([
            ['title' => 'Confirm AV', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => -1440, 'is_blocking' => false],
        ]);
        $this->tasks->instantiate($templateId, $this->eventId);

        $before = (string) DB::table('event_tasks')->where('title', 'Confirm AV')->value('due_at');

        DB::table('events')->where('id', $this->eventId)->update(['start_date' => now()->addDays(30)]);
        $this->assertSame(1, $this->tasks->reschedule($this->eventId));

        $this->assertNotSame(
            $before,
            (string) DB::table('event_tasks')->where('title', 'Confirm AV')->value('due_at')
        );
    }

    public function test_a_completed_task_keeps_the_deadline_it_was_judged_against(): void
    {
        $templateId = $this->makeTemplate([
            ['title' => 'Confirm AV', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => -1440, 'is_blocking' => false],
        ]);
        $this->tasks->instantiate($templateId, $this->eventId);

        $taskId = (int) DB::table('event_tasks')->where('title', 'Confirm AV')->value('id');
        $this->tasks->complete($taskId, $this->userId);
        $before = (string) DB::table('event_tasks')->where('id', $taskId)->value('due_at');

        DB::table('events')->where('id', $this->eventId)->update(['start_date' => now()->addDays(30)]);
        $this->tasks->reschedule($this->eventId);

        $this->assertSame(
            $before,
            (string) DB::table('event_tasks')->where('id', $taskId)->value('due_at'),
            'Moving a finished task would rewrite whether it was late.'
        );
    }

    public function test_a_task_requiring_evidence_cannot_be_completed_without_it(): void
    {
        $templateId = $this->makeTemplate([
            ['title' => 'Photograph fire exits', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => -60,
                'is_blocking' => true, 'requires_evidence' => true],
        ]);
        $this->tasks->instantiate($templateId, $this->eventId);
        $taskId = (int) DB::table('event_tasks')->value('id');

        $this->expectExceptionMessageMatches('/requires evidence/');
        $this->tasks->complete($taskId, $this->userId);
    }

    public function test_a_task_requiring_evidence_completes_with_it(): void
    {
        $templateId = $this->makeTemplate([
            ['title' => 'Photograph fire exits', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => -60,
                'is_blocking' => true, 'requires_evidence' => true],
        ]);
        $this->tasks->instantiate($templateId, $this->eventId);
        $taskId = (int) DB::table('event_tasks')->value('id');

        $this->tasks->complete($taskId, $this->userId, ['image_ids' => [1, 2]]);

        $this->assertSame('DONE', DB::table('event_tasks')->where('id', $taskId)->value('status'));
    }

    public function test_waiving_a_task_requires_a_reason(): void
    {
        $templateId = $this->makeTemplate([
            ['title' => 'Blocking thing', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => 0, 'is_blocking' => true],
        ]);
        $this->tasks->instantiate($templateId, $this->eventId);
        $taskId = (int) DB::table('event_tasks')->value('id');

        $this->expectExceptionMessageMatches('/waiver reason is required/');
        $this->tasks->waive($taskId, $this->userId, '   ');
    }

    public function test_outstanding_blockers_exclude_done_and_waived(): void
    {
        $templateId = $this->makeTemplate([
            ['title' => 'One', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => 0, 'is_blocking' => true],
            ['title' => 'Two', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => 0, 'is_blocking' => true],
            ['title' => 'Three', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => 0, 'is_blocking' => true],
            ['title' => 'Advisory', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => 0, 'is_blocking' => false],
        ]);
        $this->tasks->instantiate($templateId, $this->eventId);

        $this->tasks->complete((int) DB::table('event_tasks')->where('title', 'One')->value('id'), $this->userId);
        $this->tasks->waive((int) DB::table('event_tasks')->where('title', 'Two')->value('id'), $this->userId, 'Accepted');

        $blockers = $this->tasks->outstandingBlockers($this->eventId);

        $this->assertCount(1, $blockers);
        $this->assertSame('Three', $blockers[0]->title);
    }

    public function test_a_template_from_another_account_is_refused(): void
    {
        $stranger = User::factory()->withAccount()->create();
        $foreignTemplate = $this->makeTemplate(
            [['title' => 'Foreign', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => 0, 'is_blocking' => false]],
            accountId: (int) $stranger->accounts()->first()->id
        );

        $this->expectExceptionMessageMatches('/does not belong to the template/');
        $this->tasks->instantiate($foreignTemplate, $this->eventId);
    }

    // ---------------------------------------------------------------- incidents

    public function test_an_incident_is_recorded_with_a_quotable_reference(): void
    {
        $first = $this->incidents->report($this->eventId, 'Barrier down at Gate 3', 'CROWD', IncidentSeverity::SEV2, $this->userId);
        $second = $this->incidents->report($this->eventId, 'Spillage in Hall B', 'SAFETY', IncidentSeverity::SEV4, $this->userId);

        $this->assertSame('1', DB::table('incidents')->where('id', $first)->value('reference'));
        $this->assertSame('2', DB::table('incidents')->where('id', $second)->value('reference'));
    }

    public function test_reporting_writes_an_update_row(): void
    {
        $incidentId = $this->incidents->report($this->eventId, 'Thing', 'OTHER', IncidentSeverity::SEV3, $this->userId);

        $this->assertSame(1, DB::table('incident_updates')->where('incident_id', $incidentId)->count());
    }

    public function test_acknowledgement_is_stamped_once(): void
    {
        $incidentId = $this->incidents->report($this->eventId, 'Thing', 'OTHER', IncidentSeverity::SEV1, $this->userId);

        $this->incidents->transition($incidentId, IncidentStatus::ACKNOWLEDGED, $this->userId);
        $first = (string) DB::table('incidents')->where('id', $incidentId)->value('acknowledged_at');

        $this->incidents->transition($incidentId, IncidentStatus::IN_PROGRESS, $this->userId);

        $this->assertSame(
            $first,
            (string) DB::table('incidents')->where('id', $incidentId)->value('acknowledged_at'),
            'Time-to-acknowledge is the number a control room is judged on; it must not be overwritten.'
        );
    }

    public function test_resolving_requires_saying_what_was_done(): void
    {
        $incidentId = $this->incidents->report($this->eventId, 'Thing', 'OTHER', IncidentSeverity::SEV3, $this->userId);

        $this->expectExceptionMessageMatches('/requires a description of what was done/');
        $this->incidents->transition($incidentId, IncidentStatus::RESOLVED, $this->userId);
    }

    public function test_reopening_clears_the_resolution_timestamp(): void
    {
        $incidentId = $this->incidents->report($this->eventId, 'Thing', 'OTHER', IncidentSeverity::SEV3, $this->userId);

        $this->incidents->transition($incidentId, IncidentStatus::RESOLVED, $this->userId, resolution: 'Mopped');
        $this->assertNotNull(DB::table('incidents')->where('id', $incidentId)->value('resolved_at'));

        $this->incidents->transition($incidentId, IncidentStatus::IN_PROGRESS, $this->userId, note: 'Came back');

        $this->assertNull(
            DB::table('incidents')->where('id', $incidentId)->value('resolved_at'),
            'An incident that is open again was not resolved.'
        );
    }

    public function test_an_invalid_incident_transition_is_refused(): void
    {
        $incidentId = $this->incidents->report($this->eventId, 'Thing', 'OTHER', IncidentSeverity::SEV3, $this->userId);

        $this->incidents->transition($incidentId, IncidentStatus::RESOLVED, $this->userId, resolution: 'Done');
        $this->incidents->transition($incidentId, IncidentStatus::CLOSED, $this->userId);

        $this->expectExceptionMessageMatches('/cannot go from CLOSED/');
        $this->incidents->transition($incidentId, IncidentStatus::IN_PROGRESS, $this->userId);
    }

    public function test_an_incident_past_its_acknowledgement_target_is_flagged(): void
    {
        $incidentId = $this->incidents->report(
            $this->eventId,
            'Unattended',
            'SAFETY',
            IncidentSeverity::SEV1,
            $this->userId,
            occurredAt: Carbon::now()->subMinutes(30),
        );

        $breaching = $this->incidents->breachingAcknowledgement($this->eventId);

        $this->assertCount(1, $breaching);
        $this->assertSame($incidentId, (int) $breaching[0]->id);
    }

    public function test_a_recent_incident_is_not_flagged(): void
    {
        $this->incidents->report($this->eventId, 'Just now', 'SAFETY', IncidentSeverity::SEV4, $this->userId);

        $this->assertSame([], $this->incidents->breachingAcknowledgement($this->eventId));
    }

    public function test_the_incident_summary_counts_by_severity(): void
    {
        $this->incidents->report($this->eventId, 'A', 'OTHER', IncidentSeverity::SEV1, $this->userId);
        $this->incidents->report($this->eventId, 'B', 'OTHER', IncidentSeverity::SEV3, $this->userId);
        $resolved = $this->incidents->report($this->eventId, 'C', 'OTHER', IncidentSeverity::SEV3, $this->userId);
        $this->incidents->transition($resolved, IncidentStatus::RESOLVED, $this->userId, resolution: 'Fixed');

        $summary = $this->incidents->summary($this->eventId);

        $this->assertSame(3, $summary['total']);
        $this->assertSame(2, $summary['open']);
        $this->assertSame(1, $summary['by_severity']['SEV1']);
        $this->assertSame(2, $summary['by_severity']['SEV3']);
    }

    // ---------------------------------------------------------------- readiness

    public function test_a_review_freezes_the_checks_at_the_time_of_decision(): void
    {
        $templateId = $this->makeTemplate([
            ['title' => 'Blocking thing', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => 0, 'is_blocking' => true],
        ]);
        $this->tasks->instantiate($templateId, $this->eventId);

        $reviewId = $this->readiness->open($this->eventId, 'T_MINUS_2H');
        $frozen = count($this->readiness->items($reviewId));

        // Completing the task afterwards must not rewrite the snapshot.
        $this->tasks->complete((int) DB::table('event_tasks')->value('id'), $this->userId);

        $this->assertSame(
            $frozen,
            count($this->readiness->items($reviewId)),
            'After an incident the question is what was true when the decision was signed off.'
        );
    }

    public function test_a_clean_go_is_refused_while_a_blocker_is_outstanding(): void
    {
        $templateId = $this->makeTemplate([
            ['title' => 'Blocking thing', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => 0, 'is_blocking' => true],
        ]);
        $this->tasks->instantiate($templateId, $this->eventId);

        $reviewId = $this->readiness->open($this->eventId, 'T_MINUS_2H');

        $this->expectExceptionMessageMatches('/blocking check/');
        $this->readiness->decide($reviewId, 'GO', $this->userId);
    }

    public function test_a_go_is_allowed_with_an_explicit_waiver(): void
    {
        $templateId = $this->makeTemplate([
            ['title' => 'Blocking thing', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => 0, 'is_blocking' => true],
        ]);
        $this->tasks->instantiate($templateId, $this->eventId);

        $reviewId = $this->readiness->open($this->eventId, 'T_MINUS_2H');
        $blockers = array_values(array_filter(
            $this->readiness->items($reviewId),
            static fn (object $item): bool => in_array($item->status, ['FAIL', 'WARN'], true)
        ));

        $waivers = array_map(
            static fn (object $item): array => ['item_id' => (int) $item->id, 'reason' => 'Risk accepted by the event director'],
            $blockers
        );

        $this->readiness->decide($reviewId, 'GO_WITH_RISKS', $this->userId, waivers: $waivers);

        $review = DB::table('readiness_reviews')->where('id', $reviewId)->first();
        $this->assertSame('GO_WITH_RISKS', $review->decision);
        $this->assertSame($this->userId, (int) $review->decided_by);
    }

    public function test_a_waiver_without_a_reason_is_refused(): void
    {
        $templateId = $this->makeTemplate([
            ['title' => 'Blocking thing', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => 0, 'is_blocking' => true],
        ]);
        $this->tasks->instantiate($templateId, $this->eventId);

        $reviewId = $this->readiness->open($this->eventId, 'T_MINUS_2H');
        $itemId = (int) DB::table('readiness_items')->where('readiness_review_id', $reviewId)->value('id');

        $this->expectExceptionMessageMatches('/must say why the risk is accepted/');
        $this->readiness->decide($reviewId, 'GO', $this->userId, waivers: [['item_id' => $itemId, 'reason' => '  ']]);
    }

    public function test_a_no_go_never_needs_a_waiver(): void
    {
        $templateId = $this->makeTemplate([
            ['title' => 'Blocking thing', 'anchor' => 'DOORS_OPEN', 'offset_minutes' => 0, 'is_blocking' => true],
        ]);
        $this->tasks->instantiate($templateId, $this->eventId);

        $reviewId = $this->readiness->open($this->eventId, 'T_MINUS_2H');

        $this->readiness->decide($reviewId, 'NO_GO', $this->userId, notes: 'Hall not ready');

        $this->assertSame(
            'NO_GO',
            DB::table('readiness_reviews')->where('id', $reviewId)->value('decision'),
            'Recording that an event is not ready must never be harder than overriding.'
        );
    }

    public function test_a_review_cannot_be_decided_twice(): void
    {
        $reviewId = $this->readiness->open($this->eventId, 'T_MINUS_24H');
        $this->readiness->decide($reviewId, 'NO_GO', $this->userId);

        $this->expectExceptionMessageMatches('/already been decided/');
        $this->readiness->decide($reviewId, 'GO', $this->userId);
    }

    public function test_an_unknown_decision_is_refused(): void
    {
        $reviewId = $this->readiness->open($this->eventId, 'AD_HOC');

        $this->expectException(ResourceConflictException::class);
        $this->readiness->decide($reviewId, 'MAYBE', $this->userId);
    }

    public function test_understaffing_fails_the_staffing_check(): void
    {
        $this->makeShift('2026-11-01 09:00:00+00', '2026-11-01 17:00:00+00', headcount: 3);

        $reviewId = $this->readiness->open($this->eventId, 'T_MINUS_2H');

        $staffing = array_values(array_filter(
            $this->readiness->items($reviewId),
            static fn (object $item): bool => $item->check_key === 'staffing.headcount'
        ));

        $this->assertSame('FAIL', $staffing[0]->status);
    }

    public function test_an_open_sev1_fails_the_incident_check(): void
    {
        $this->incidents->report($this->eventId, 'Fire panel fault', 'SAFETY', IncidentSeverity::SEV1, $this->userId);

        $reviewId = $this->readiness->open($this->eventId, 'T_MINUS_2H');

        $incidents = array_values(array_filter(
            $this->readiness->items($reviewId),
            static fn (object $item): bool => $item->check_key === 'incidents.no_open_sev1'
        ));

        $this->assertSame('FAIL', $incidents[0]->status);
    }

    public function test_a_waiver_row_cannot_exist_without_a_reason_in_the_database(): void
    {
        $reviewId = $this->readiness->open($this->eventId, 'AD_HOC');

        $this->expectException(QueryException::class);

        DB::table('readiness_items')->insert([
            'readiness_review_id' => $reviewId,
            'title' => 'Sneaky waiver',
            'severity' => 'BLOCKING',
            'status' => 'WAIVED',
            'evaluated_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------- fixtures

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function makeTemplate(array $items, ?int $accountId = null): int
    {
        $templateId = (int) DB::table('task_templates')->insertGetId([
            'short_id' => 'tt_'.Str::lower(Str::random(20)),
            'account_id' => $accountId ?? $this->accountId,
            'name' => 'Standard checklist',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($items as $index => $item) {
            DB::table('task_template_items')->insert([
                'task_template_id' => $templateId,
                'title' => $item['title'],
                'anchor' => $item['anchor'],
                'offset_minutes' => $item['offset_minutes'],
                'is_blocking' => $item['is_blocking'] ?? false,
                'requires_evidence' => $item['requires_evidence'] ?? false,
                'sort_order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $templateId;
    }

    private function makeShift(string $startsAt, string $endsAt, int $headcount = 1): int
    {
        return (int) DB::table('shifts')->insertGetId([
            'short_id' => 'sf_'.Str::lower(Str::random(20)),
            'staff_position_id' => $this->positionId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'required_headcount' => $headcount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePosition(): int
    {
        return (int) DB::table('staff_positions')->insertGetId([
            'short_id' => 'sp_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'name' => 'Gate 3 scanner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePerson(?int $accountId = null): int
    {
        return (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $accountId ?? $this->accountId,
            'first_name' => 'Ops',
            'last_name' => 'Person',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Ops Organizer',
            'email' => 'ops-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Ops Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(14),
            'end_date' => now()->addDays(15),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
