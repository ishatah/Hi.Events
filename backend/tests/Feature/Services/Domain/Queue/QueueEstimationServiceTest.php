<?php

namespace Tests\Feature\Services\Domain\Queue;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\QueueState;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Queue\QueueEstimationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueEstimationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private QueueEstimationService $service;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $venueId;

    private int $zoneId;

    private int $accessPointId;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(QueueEstimationService::class);
        $this->now = CarbonImmutable::parse('2026-10-05 14:00:00', 'UTC');

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent();
        $this->venueId = $this->makeVenue();
        $this->zoneId = $this->makeZone();
        $this->accessPointId = $this->makeAccessPoint();
    }

    public function test_throughput_counts_granted_entries_per_minute(): void
    {
        $this->scans(granted: 60, denied: 0);

        $estimate = $this->service->estimate($this->accessPointId, 300, $this->now);

        $this->assertSame(60, $estimate->scansGranted);
        $this->assertSame(12.0, $estimate->throughputPerMinute);
    }

    public function test_denials_count_towards_arrivals_but_not_throughput(): void
    {
        $this->scans(granted: 30, denied: 30);

        $estimate = $this->service->estimate($this->accessPointId, 300, $this->now);

        $this->assertSame(
            6.0,
            $estimate->throughputPerMinute,
            'Only people who got through count as throughput.'
        );
        $this->assertSame(
            12.0,
            $estimate->arrivalsPerMinute,
            'A gate denying many scans is slower, not faster — each denial consumes staff '
            .'time, so counting only successes would show a congested door as quiet.'
        );
    }

    public function test_an_overridden_entry_still_counts_as_someone_getting_through(): void
    {
        foreach (range(1, 30) as $i) {
            $this->scan($this->now->subSeconds(200 - $i), 'GRANTED_OVERRIDE');
        }

        $this->assertSame(
            30,
            $this->service->estimate($this->accessPointId, 300, $this->now)->scansGranted,
            'A supervisor waving somebody through moved the queue; counting it as a denial '
            .'would report a working door as blocked.'
        );
    }

    public function test_a_door_whose_arrivals_outpace_throughput_reports_a_backlog(): void
    {
        $this->scans(granted: 20, denied: 100);

        $estimate = $this->service->estimate($this->accessPointId, 300, $this->now);

        $this->assertGreaterThan(0, $estimate->estimatedQueueDepth);
        $this->assertSame(QueueState::CONGESTED, $estimate->state);
    }

    public function test_a_door_keeping_up_reports_no_backlog(): void
    {
        $this->scans(granted: 40, denied: 0);

        $estimate = $this->service->estimate($this->accessPointId, 300, $this->now);

        $this->assertSame(0, $estimate->estimatedQueueDepth);
        $this->assertSame(0, $estimate->estimatedWaitSecondsLow);
        $this->assertSame(QueueState::FLOWING, $estimate->state);
    }

    public function test_a_door_nobody_used_is_idle_rather_than_flowing(): void
    {
        $estimate = $this->service->estimate($this->accessPointId, 300, $this->now);

        $this->assertSame(
            QueueState::IDLE,
            $estimate->state,
            'An idle gate and a fast gate need different responses: one wants signage, the '
            .'other wants leaving alone.'
        );
    }

    public function test_the_wait_is_a_range_not_a_single_number(): void
    {
        $this->scans(granted: 20, denied: 80);

        $estimate = $this->service->estimate($this->accessPointId, 300, $this->now);

        $this->assertNotNull($estimate->estimatedWaitSecondsLow);
        $this->assertNotNull($estimate->estimatedWaitSecondsHigh);
        $this->assertGreaterThan(
            $estimate->estimatedWaitSecondsLow,
            $estimate->estimatedWaitSecondsHigh,
            'A wait shown as one confident figure will be wrong and will be quoted back.'
        );
    }

    public function test_a_wait_with_no_throughput_is_unknown_rather_than_zero(): void
    {
        $this->scans(granted: 0, denied: 40);

        $estimate = $this->service->estimate($this->accessPointId, 300, $this->now);

        $this->assertGreaterThan(0, $estimate->estimatedQueueDepth);
        $this->assertNull(
            $estimate->estimatedWaitSecondsLow,
            'Nobody is getting through, so the wait is unknown; a number here would be '
            .'derived from nothing.'
        );
    }

    public function test_an_implausible_wait_is_capped_rather_than_printed(): void
    {
        $this->scan($this->now->subSeconds(290), 'GRANTED');

        foreach (range(1, 120) as $i) {
            $this->scan($this->now->subSeconds(280 - $i), 'DENIED_NO_GRANT');
        }

        $estimate = $this->service->estimate($this->accessPointId, 300, $this->now);

        $this->assertSame(
            3600,
            $estimate->estimatedWaitSecondsHigh,
            'A multi-hour figure reads as broken rather than urgent, and says nothing the '
            .'CONGESTED state has not already said.'
        );
    }

    public function test_median_service_time_needs_enough_scans_to_mean_anything(): void
    {
        $this->scans(granted: 2, denied: 0);

        $this->assertNull($this->service->estimate($this->accessPointId, 300, $this->now)->medianServiceSeconds);
    }

    public function test_median_service_time_is_the_gap_between_consecutive_scans(): void
    {
        foreach ([0, 10, 20, 30, 40] as $offset) {
            $this->scan($this->now->subSeconds(120 - $offset), 'GRANTED');
        }

        $this->assertSame(
            10.0,
            $this->service->estimate($this->accessPointId, 300, $this->now)->medianServiceSeconds
        );
    }

    public function test_scans_outside_the_window_are_ignored(): void
    {
        $this->scan($this->now->subSeconds(30), 'GRANTED');
        $this->scan($this->now->subMinutes(45), 'GRANTED');

        $this->assertSame(1, $this->service->estimate($this->accessPointId, 300, $this->now)->scansGranted);
    }

    public function test_exit_scans_do_not_count_as_arrivals(): void
    {
        $this->scan($this->now->subSeconds(30), 'GRANTED');
        $this->scan($this->now->subSeconds(20), 'GRANTED', direction: 'EXIT');

        $this->assertSame(
            1,
            $this->service->estimate($this->accessPointId, 300, $this->now)->scansGranted,
            'People leaving are not queueing to get in.'
        );
    }

    public function test_a_door_scanning_a_different_access_point_is_not_counted(): void
    {
        $other = $this->makeAccessPoint('Gate 2');

        $this->scan($this->now->subSeconds(30), 'GRANTED', accessPointId: $other);

        $this->assertSame(0, $this->service->estimate($this->accessPointId, 300, $this->now)->scansGranted);
    }

    public function test_a_high_denial_share_reads_as_a_rule_problem_not_a_crowd(): void
    {
        $this->scans(granted: 5, denied: 45);

        $estimate = $this->service->estimate($this->accessPointId, 300, $this->now);

        $this->assertTrue(
            $estimate->suggestsMisconfiguration(),
            'A door denying most scans usually has a bad rule, and sending more staff to it '
            .'would not help.'
        );
    }

    public function test_a_normal_denial_rate_is_not_flagged(): void
    {
        $this->scans(granted: 95, denied: 5);

        $this->assertFalse($this->service->estimate($this->accessPointId, 300, $this->now)->suggestsMisconfiguration());
    }

    public function test_a_few_denials_do_not_flag_a_quiet_door(): void
    {
        $this->scans(granted: 1, denied: 2);

        $this->assertFalse(
            $this->service->estimate($this->accessPointId, 300, $this->now)->suggestsMisconfiguration(),
            'Three scans is not evidence of anything.'
        );
    }

    public function test_capturing_persists_the_window(): void
    {
        $this->scans(granted: 10, denied: 5, withinSeconds: 60);

        $this->service->capture($this->accessPointId, $this->eventId, 60, $this->now);

        $row = DB::table('access_point_throughput_snapshots')
            ->where('access_point_id', $this->accessPointId)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(10, (int) $row->scans_granted);
        $this->assertSame(5, (int) $row->scans_denied);
    }

    public function test_the_event_view_ranks_the_worst_door_first(): void
    {
        $quiet = $this->makeAccessPoint('Quiet Gate');
        $this->scans(granted: 20, denied: 0, accessPointId: $quiet);
        $this->scans(granted: 5, denied: 90);

        $estimates = $this->service->estimateForEvent($this->eventId, 300, $this->now);

        $this->assertGreaterThanOrEqual(2, $estimates->count());
        $this->assertSame(
            $this->accessPointId,
            $estimates->first()->accessPointId,
            'An operator opens this to find the door in trouble, not to read a list.'
        );
    }

    public function test_the_projection_reports_its_basis(): void
    {
        $projection = $this->service->projectDepth($this->accessPointId);

        $this->assertSame(0, $projection['basis_windows']);
        $this->assertSame(0, $projection['projected_depth']);
    }

    public function test_a_rising_queue_projects_higher(): void
    {
        foreach ([5, 10, 18, 27] as $index => $depth) {
            DB::table('access_point_throughput_snapshots')->insert([
                'access_point_id' => $this->accessPointId,
                'event_id' => $this->eventId,
                'window_start' => $this->now->subMinutes(4 - $index),
                'window_end' => $this->now->subMinutes(3 - $index),
                'scans_granted' => 10,
                'scans_denied' => 0,
                'estimated_queue_depth' => $depth,
                'created_at' => now(),
            ]);
        }

        $projection = $this->service->projectDepth($this->accessPointId, 10);

        $this->assertGreaterThan(0, $projection['trend_per_minute']);
        $this->assertGreaterThan(
            27,
            $projection['projected_depth'],
            'Alerting on the trend rather than the current depth is the difference between '
            .'warning staff and telling them what they can already see.'
        );
    }

    public function test_a_falling_queue_does_not_project_below_zero(): void
    {
        foreach ([30, 20, 10, 2] as $index => $depth) {
            DB::table('access_point_throughput_snapshots')->insert([
                'access_point_id' => $this->accessPointId,
                'event_id' => $this->eventId,
                'window_start' => $this->now->subMinutes(4 - $index),
                'window_end' => $this->now->subMinutes(3 - $index),
                'scans_granted' => 10,
                'scans_denied' => 0,
                'estimated_queue_depth' => $depth,
                'created_at' => now(),
            ]);
        }

        $this->assertGreaterThanOrEqual(0, $this->service->projectDepth($this->accessPointId, 60)['projected_depth']);
    }

    // ---------------------------------------------------------------- fixtures

    private function scans(int $granted, int $denied, int $withinSeconds = 300, ?int $accessPointId = null): void
    {
        $total = $granted + $denied;
        $step = $total > 0 ? max(1, intdiv($withinSeconds - 10, max(1, $total))) : 1;
        $offset = $withinSeconds - 5;

        for ($i = 0; $i < $granted; $i++) {
            $this->scan($this->now->subSeconds($offset), 'GRANTED', accessPointId: $accessPointId);
            $offset = max(1, $offset - $step);
        }

        for ($i = 0; $i < $denied; $i++) {
            $this->scan($this->now->subSeconds($offset), 'DENIED_NO_GRANT', accessPointId: $accessPointId);
            $offset = max(1, $offset - $step);
        }
    }

    private function scan(
        CarbonImmutable $occurredAt,
        string $result,
        string $direction = 'ENTRY',
        ?int $accessPointId = null,
    ): void {
        DB::table('access_logs')->insert([
            'short_id' => 'al_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'access_point_id' => $accessPointId ?? $this->accessPointId,
            'zone_id' => $this->zoneId,
            'occurred_at' => $occurredAt,
            'recorded_at' => $occurredAt,
            'direction' => $direction,
            'result' => $result,
            'raw_identifier' => Str::upper(Str::random(12)),
            'identifier_type' => 'QR',
            'source' => 'SCANNER',
            'created_at' => now(),
        ]);
    }

    private function makeAccessPoint(string $name = 'Gate 1'): int
    {
        return (int) DB::table('access_points')->insertGetId([
            'short_id' => 'ap_'.Str::lower(Str::random(20)),
            'zone_id' => $this->zoneId,
            'name' => $name,
            'code' => Str::upper(Str::random(8)),
            'direction' => 'BIDIRECTIONAL',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeZone(): int
    {
        return (int) DB::table('zones')->insertGetId([
            'short_id' => 'zn_'.Str::lower(Str::random(20)),
            'venue_id' => $this->venueId,
            'name' => 'Main Hall',
            'code' => Str::upper(Str::random(8)),
            'zone_type' => 'GENERAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeVenue(): int
    {
        $venueId = (int) DB::table('venues')->insertGetId([
            'short_id' => 'vn_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Queue Venue',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('event_venues')->insert([
            'event_id' => $this->eventId,
            'venue_id' => $venueId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $venueId;
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Queue Organizer',
            'email' => 'q-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Queue Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDay(),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
