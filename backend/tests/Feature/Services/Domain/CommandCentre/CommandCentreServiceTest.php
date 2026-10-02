<?php

namespace Tests\Feature\Services\Domain\CommandCentre;

use Carbon\CarbonImmutable;
use HiEvents\Models\User;
use HiEvents\Services\Domain\CommandCentre\CommandCentreService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommandCentreServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CommandCentreService $service;

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

        $this->service = app(CommandCentreService::class);
        $this->now = CarbonImmutable::parse('2026-10-06 14:00:00', 'UTC');

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent();
        $this->venueId = $this->makeVenue();
        $this->zoneId = $this->makeZone(capacity: 100);
        $this->accessPointId = $this->makeAccessPoint();
    }

    // ---------------------------------------------------------------- staleness

    public function test_a_fleet_that_has_all_checked_in_reports_live_figures(): void
    {
        $this->makeDevice(lastSeenAt: $this->now->subMinutes(2));

        $staleness = $this->service->staleness($this->eventId, $this->now);

        $this->assertFalse($staleness['is_provisional']);
        $this->assertSame(0, $staleness['devices_stale']);
    }

    public function test_a_device_that_has_not_synced_makes_the_figures_provisional(): void
    {
        $this->makeDevice(name: 'Gate 3 Scanner', lastSeenAt: $this->now->subMinutes(45));

        $staleness = $this->service->staleness($this->eventId, $this->now);

        $this->assertTrue(
            $staleness['is_provisional'],
            'A dashboard that looks live while showing stale data is worse than one that '
            .'admits it: the operator has to know what they do not know.'
        );
        $this->assertSame(1, $staleness['devices_stale']);
        $this->assertContains('Gate 3 Scanner', $staleness['stale_device_names']);
        $this->assertSame(45, $staleness['oldest_sync_minutes_ago']);
    }

    public function test_an_event_with_no_devices_is_not_reported_as_stale(): void
    {
        $staleness = $this->service->staleness($this->eventId, $this->now);

        $this->assertFalse($staleness['is_provisional']);
        $this->assertSame(
            0,
            $staleness['devices_total'],
            'An event with no devices has nothing unsynced, but the device count has to be '
            .'visible so nobody reads this as figures somebody verified.'
        );
        $this->assertNull($staleness['oldest_sync_at']);
    }

    public function test_a_device_that_has_never_checked_in_counts_as_stale(): void
    {
        $this->makeDevice(lastSeenAt: null);

        $this->assertTrue($this->service->staleness($this->eventId, $this->now)['is_provisional']);
    }

    public function test_a_retired_device_does_not_make_the_screen_look_stale(): void
    {
        $this->makeDevice(lastSeenAt: $this->now->subDays(30), status: 'RETIRED');
        $this->makeDevice(lastSeenAt: $this->now->subMinute());

        $this->assertFalse(
            $this->service->staleness($this->eventId, $this->now)['is_provisional'],
            'Equipment taken out of service is not equipment that has stopped reporting.'
        );
    }

    // ---------------------------------------------------------------- admission

    public function test_arrivals_are_counted_from_the_log_not_a_stored_counter(): void
    {
        $this->attendee();
        $this->attendee();
        $this->attendee();

        $first = $this->makePerson();
        $this->scan($first, $this->now->subMinutes(2));
        $this->scan($this->makePerson(), $this->now->subMinute());

        $admission = $this->service->admission($this->eventId, $this->now);

        $this->assertSame(3, $admission['expected']);
        $this->assertSame(2, $admission['arrived']);
        $this->assertSame(1, $admission['not_arrived']);
    }

    public function test_re_entry_does_not_inflate_the_arrived_count(): void
    {
        $this->attendee();
        $person = $this->makePerson();

        $this->scan($person, $this->now->subMinutes(3));
        $this->scan($person, $this->now->subMinute());

        $this->assertSame(
            1,
            $this->service->admission($this->eventId, $this->now)['arrived'],
            'Somebody stepping out for a coffee has not arrived twice.'
        );
    }

    public function test_denials_are_broken_out_by_reason(): void
    {
        $this->scan($this->makePerson(), $this->now->subMinute());
        $this->scan($this->makePerson(), $this->now->subMinute(), 'DENIED_NO_GRANT');
        $this->scan($this->makePerson(), $this->now->subMinute(), 'DENIED_NO_GRANT');
        $this->scan($this->makePerson(), $this->now->subMinute(), 'DENIED_CAPACITY');

        $admission = $this->service->admission($this->eventId, $this->now);

        $this->assertEqualsCanonicalizing(
            ['DENIED_NO_GRANT' => 2, 'DENIED_CAPACITY' => 1],
            $admission['denials_by_reason'],
            'A spike in DENIED_NO_GRANT is a misconfigured rule and a spike in '
            .'DENIED_CAPACITY is a full room; one denied figure hides which it is.'
        );
        $this->assertSame(0.75, $admission['denial_rate']);
    }

    public function test_an_overridden_entry_counts_as_granted(): void
    {
        $this->scan($this->makePerson(), $this->now->subMinute(), 'GRANTED_OVERRIDE');

        $admission = $this->service->admission($this->eventId, $this->now);

        $this->assertSame([], $admission['denials_by_reason']);
        $this->assertSame(1, $admission['arrived']);
    }

    public function test_an_event_with_no_expected_figure_has_no_percentage(): void
    {
        $this->scan($this->makePerson(), $this->now->subMinute());

        $this->assertNull(
            $this->service->admission($this->eventId, $this->now)['arrived_share'],
            'An event selling on the door has no denominator, and a percentage of nothing '
            .'reads as nobody arriving.'
        );
    }

    public function test_scans_outside_the_window_do_not_affect_the_current_rate(): void
    {
        $this->scan($this->makePerson(), $this->now->subHours(3));

        $admission = $this->service->admission($this->eventId, $this->now);

        $this->assertSame(0.0, $admission['arrivals_per_minute']);
        $this->assertSame(
            1,
            $admission['arrived'],
            'They are still in the building even if they arrived hours ago.'
        );
    }

    // ---------------------------------------------------------------- occupancy

    public function test_occupancy_reads_the_latest_snapshot(): void
    {
        $this->snapshot(40, $this->now->subMinutes(5));
        $this->snapshot(75, $this->now->subSeconds(20));

        $occupancy = $this->service->occupancy($this->eventId);

        $this->assertCount(1, $occupancy);
        $this->assertSame(75, $occupancy[0]['occupancy']);
        $this->assertSame(0.75, $occupancy[0]['utilisation']);
        $this->assertFalse($occupancy[0]['at_capacity']);
    }

    public function test_the_newest_snapshot_wins_even_when_rows_arrive_out_of_order(): void
    {
        // A backfill or a replayed sync can write an older measurement after a newer one.
        // The dashboard has to show the most recent reading, not the most recently written
        // row, or it silently reports a stale number as current.
        $this->snapshot(90, $this->now->subSeconds(20));
        $this->snapshot(30, $this->now->subMinutes(10));

        $this->assertSame(
            90,
            $this->service->occupancy($this->eventId)[0]['occupancy'],
            'The newest measurement wins, whatever order the rows were inserted in.'
        );
    }

    public function test_a_zone_with_no_snapshot_reads_as_unknown_rather_than_empty(): void
    {
        $occupancy = $this->service->occupancy($this->eventId);

        $this->assertNull(
            $occupancy[0]['occupancy'],
            'Zero would read as an empty room when the truth is that nobody has measured it.'
        );
        $this->assertNull($occupancy[0]['utilisation']);
        $this->assertFalse($occupancy[0]['at_capacity']);
    }

    public function test_a_full_zone_is_flagged(): void
    {
        $this->snapshot(100, $this->now->subSeconds(10));

        $this->assertTrue($this->service->occupancy($this->eventId)[0]['at_capacity']);
    }

    public function test_a_zone_with_no_capacity_has_no_utilisation(): void
    {
        $uncappedZone = $this->makeZone(capacity: null);

        DB::table('zone_occupancy_snapshots')->insert([
            'zone_id' => $uncappedZone,
            'event_id' => $this->eventId,
            'occupancy' => 500,
            'capacity' => null,
            'captured_at' => $this->now->subSeconds(10),
        ]);

        $uncapped = collect($this->service->occupancy($this->eventId))
            ->firstWhere('zone_id', $uncappedZone);

        $this->assertNull($uncapped['utilisation']);
        $this->assertFalse($uncapped['at_capacity']);
    }

    // ---------------------------------------------------------------- fleet

    public function test_the_fleet_separates_online_from_offline(): void
    {
        $this->makeDevice(lastSeenAt: $this->now->subMinute());
        $this->makeDevice(name: 'Dead Scanner', lastSeenAt: $this->now->subHour());

        $fleet = $this->service->fleet($this->eventId, $this->now);

        $this->assertSame(2, $fleet['total']);
        $this->assertSame(1, $fleet['online']);
        $this->assertSame(1, $fleet['offline']);
        $this->assertSame('Dead Scanner', $fleet['offline_devices'][0]['name']);
    }

    public function test_low_batteries_are_listed_before_they_become_outages(): void
    {
        $this->makeDevice(name: 'Nearly Flat', lastSeenAt: $this->now, batteryLevel: 8);
        $this->makeDevice(lastSeenAt: $this->now, batteryLevel: 90);

        $fleet = $this->service->fleet($this->eventId, $this->now);

        $this->assertCount(1, $fleet['low_battery']);
        $this->assertSame('Nearly Flat', $fleet['low_battery'][0]['name']);
    }

    public function test_mixed_app_versions_are_surfaced(): void
    {
        $this->makeDevice(lastSeenAt: $this->now, appVersion: '1.4.0');
        $this->makeDevice(lastSeenAt: $this->now, appVersion: '1.4.0');
        $this->makeDevice(lastSeenAt: $this->now, appVersion: '1.2.1');

        $this->assertSame(
            ['1.4.0' => 2, '1.2.1' => 1],
            $this->service->fleet($this->eventId, $this->now)['app_versions']
        );
    }

    // ---------------------------------------------------------------- incidents

    public function test_open_incidents_are_grouped_by_severity(): void
    {
        $this->incident('SEV1', 'OPEN');
        $this->incident('SEV2', 'OPEN');
        $this->incident('SEV2', 'ACKNOWLEDGED');
        $this->incident('SEV3', 'RESOLVED');

        $incidents = $this->service->incidents($this->eventId);

        $this->assertSame(['SEV1' => 1, 'SEV2' => 2], $incidents['open_by_severity']);
        $this->assertSame(
            3,
            $incidents['open_total'],
            'A resolved incident is not something anybody needs to look at now.'
        );
    }

    public function test_access_overrides_are_counted_for_attribution(): void
    {
        $this->scan($this->makePerson(), $this->now->subMinute(), 'GRANTED_OVERRIDE');
        $this->scan($this->makePerson(), $this->now->subMinute(), 'GRANTED');

        $this->assertSame(1, $this->service->incidents($this->eventId)['access_overrides']);
    }

    // ---------------------------------------------------------------- snapshot and alerts

    public function test_the_snapshot_brings_everything_together(): void
    {
        $this->makeDevice(lastSeenAt: $this->now->subMinute());
        $this->attendee();
        $this->scan($this->makePerson(), $this->now->subMinute());
        $this->snapshot(50, $this->now->subSeconds(10));

        $snapshot = $this->service->snapshot($this->eventId, $this->now);

        foreach (['staleness', 'admission', 'queues', 'occupancy', 'fleet', 'incidents', 'staffing', 'alerts'] as $section) {
            $this->assertArrayHasKey($section, $snapshot);
        }

        $this->assertSame($this->now->toIso8601String(), $snapshot['generated_at']);
    }

    public function test_a_full_zone_raises_a_high_alert(): void
    {
        $this->snapshot(100, $this->now->subSeconds(10));

        $alerts = collect($this->service->snapshot($this->eventId, $this->now)['alerts']);
        $zoneAlert = $alerts->firstWhere('code', 'ZONE_AT_CAPACITY');

        $this->assertNotNull($zoneAlert);
        $this->assertSame('HIGH', $zoneAlert['severity']);
    }

    public function test_offline_devices_raise_an_alert(): void
    {
        $this->makeDevice(lastSeenAt: $this->now->subHour());

        $alerts = collect($this->service->snapshot($this->eventId, $this->now)['alerts']);

        $this->assertNotNull($alerts->firstWhere('code', 'DEVICES_OFFLINE'));
    }

    public function test_a_door_denying_most_scans_is_reported_as_a_rule_problem(): void
    {
        $this->scan($this->makePerson(), $this->now->subMinutes(2));

        foreach (range(1, 20) as $ignored) {
            $this->scan($this->makePerson(), $this->now->subMinute(), 'DENIED_NO_GRANT');
        }

        $alerts = collect($this->service->snapshot($this->eventId, $this->now)['alerts']);

        $this->assertNotNull(
            $alerts->firstWhere('code', 'DENIALS_CLUSTERED'),
            'Sending more staff to a door with a bad rule would not help.'
        );
    }

    public function test_a_high_denial_rate_across_the_event_raises_an_alert(): void
    {
        $this->scan($this->makePerson(), $this->now->subMinute());

        foreach (range(1, 5) as $ignored) {
            $this->scan($this->makePerson(), $this->now->subMinute(), 'DENIED_REVOKED');
        }

        $alerts = collect($this->service->snapshot($this->eventId, $this->now)['alerts']);

        $this->assertNotNull($alerts->firstWhere('code', 'DENIAL_RATE_HIGH'));
    }

    public function test_a_quiet_event_raises_no_alerts(): void
    {
        $this->makeDevice(lastSeenAt: $this->now->subMinute());
        $this->snapshot(10, $this->now->subSeconds(10));

        $this->assertSame(
            [],
            $this->service->snapshot($this->eventId, $this->now)['alerts'],
            'A dashboard that alerts on everything trains people to ignore it.'
        );
    }

    public function test_high_alerts_sort_before_medium_ones(): void
    {
        $this->snapshot(100, $this->now->subSeconds(10));
        $this->makeDevice(lastSeenAt: $this->now->subHour());

        $alerts = $this->service->snapshot($this->eventId, $this->now)['alerts'];

        $this->assertSame('HIGH', $alerts[0]['severity']);
    }

    public function test_another_events_figures_are_not_included(): void
    {
        $otherEventId = $this->makeEvent();

        DB::table('access_logs')->insert([
            'short_id' => 'al_'.Str::lower(Str::random(20)),
            'event_id' => $otherEventId,
            'zone_id' => null,
            'person_id' => $this->makePerson(),
            'occurred_at' => $this->now->subMinute(),
            'recorded_at' => $this->now->subMinute(),
            'direction' => 'ENTRY',
            'result' => 'GRANTED',
            'identifier_hash' => Str::upper(Str::random(12)),
            'identifier_type' => 'QR',
            'source' => 'SCANNER',
            'created_at' => now(),
        ]);

        $this->assertSame(0, $this->service->admission($this->eventId, $this->now)['arrived']);
    }

    // ---------------------------------------------------------------- fixtures

    private function incident(string $severity, string $status): void
    {
        DB::table('incidents')->insert([
            'short_id' => 'in_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'reference' => (string) random_int(1000, 9999),
            'title' => 'Barrier down',
            'category' => 'SAFETY',
            'severity' => $severity,
            'status' => $status,
            'occurred_at' => $this->now->subMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function snapshot(int $occupancy, CarbonImmutable $capturedAt): void
    {
        DB::table('zone_occupancy_snapshots')->insert([
            'zone_id' => $this->zoneId,
            'event_id' => $this->eventId,
            'occupancy' => $occupancy,
            'capacity' => 100,
            'captured_at' => $capturedAt,
        ]);
    }

    private function makeDevice(
        string $name = 'Gate Scanner',
        ?CarbonImmutable $lastSeenAt = null,
        string $status = 'ACTIVE',
        ?int $batteryLevel = null,
        ?string $appVersion = null,
    ): int {
        return (int) DB::table('devices')->insertGetId([
            'short_id' => 'dv_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'event_id' => $this->eventId,
            'access_point_id' => $this->accessPointId,
            'name' => $name,
            'device_type' => 'SCANNER',
            'platform' => 'ANDROID',
            'app_version' => $appVersion,
            'status' => $status,
            'last_seen_at' => $lastSeenAt,
            'battery_level' => $batteryLevel,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attendee(): int
    {
        $productId = (int) DB::table('products')->insertGetId([
            'title' => 'Command Ticket',
            'event_id' => $this->eventId,
            'type' => 'FREE',
            'product_type' => 'TICKET',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productPriceId = (int) DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = (int) DB::table('orders')->insertGetId([
            'short_id' => 'or_'.Str::lower(Str::random(16)),
            'public_id' => 'O-'.Str::upper(Str::random(10)),
            'event_id' => $this->eventId,
            'status' => 'COMPLETED',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'first_name' => 'Command',
            'last_name' => 'Buyer',
            'currency' => 'QAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('attendees')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(16)),
            'public_id' => 'A-'.Str::upper(Str::random(10)),
            'first_name' => 'Command',
            'last_name' => 'Attendee',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $productPriceId,
            'event_id' => $this->eventId,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function scan(int $personId, CarbonImmutable $occurredAt, string $result = 'GRANTED'): void
    {
        DB::table('access_logs')->insert([
            'short_id' => 'al_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'access_point_id' => $this->accessPointId,
            'zone_id' => $this->zoneId,
            'person_id' => $personId,
            'occurred_at' => $occurredAt,
            'recorded_at' => $occurredAt,
            'direction' => 'ENTRY',
            'result' => $result,
            'identifier_hash' => Str::upper(Str::random(12)),
            'identifier_type' => 'QR',
            'source' => 'SCANNER',
            'created_at' => now(),
        ]);
    }

    private function makePerson(): int
    {
        return (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Visitor',
            'last_name' => Str::upper(Str::random(5)),
            'email' => Str::lower(Str::random(12)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAccessPoint(): int
    {
        return (int) DB::table('access_points')->insertGetId([
            'short_id' => 'ap_'.Str::lower(Str::random(20)),
            'zone_id' => $this->zoneId,
            'name' => 'Gate 1',
            'code' => Str::upper(Str::random(8)),
            'direction' => 'BIDIRECTIONAL',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeZone(?int $capacity): int
    {
        return (int) DB::table('zones')->insertGetId([
            'short_id' => 'zn_'.Str::lower(Str::random(20)),
            'venue_id' => $this->venueId,
            'name' => 'Main Hall',
            'code' => Str::upper(Str::random(8)),
            'zone_type' => 'GENERAL',
            'capacity' => $capacity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeVenue(): int
    {
        $venueId = (int) DB::table('venues')->insertGetId([
            'short_id' => 'vn_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Command Venue',
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
            'name' => 'Command Organizer',
            'email' => 'cc-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Command Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now(),
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
