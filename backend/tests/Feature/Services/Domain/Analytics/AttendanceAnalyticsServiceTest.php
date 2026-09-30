<?php

namespace Tests\Feature\Services\Domain\Analytics;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\AccessDirection;
use HiEvents\DomainObjects\Enums\AccessResult;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Analytics\AttendanceAnalyticsService;
use HiEvents\Services\Domain\Credential\CredentialIdentifierService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceAnalyticsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AttendanceAnalyticsService $analytics;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $venueId;

    private int $orderId;

    private int $productId;

    private int $productPriceId;

    private int $checkInListId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analytics = app(AttendanceAnalyticsService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;

        $this->eventId = $this->makeEvent();
        $this->venueId = $this->makeVenue();
        $this->makeOrderScaffolding();
        $this->checkInListId = $this->makeCheckInList();
    }

    // ---------------------------------------------------------------- event attendance

    public function test_attendance_counts_arrivals_against_expected(): void
    {
        $arrived = $this->makeAttendee();
        $this->makeAttendee();
        $this->makeAttendee();

        $this->checkIn($arrived);

        $stats = $this->analytics->eventAttendance($this->eventId);

        $this->assertSame(3, $stats['expected']);
        $this->assertSame(1, $stats['arrived']);
        $this->assertSame(2, $stats['no_shows']);
        $this->assertSame(0.3333, $stats['attendance_rate']);
    }

    public function test_a_cancelled_attendee_is_not_expected(): void
    {
        $this->makeAttendee();
        $cancelled = $this->makeAttendee();
        DB::table('attendees')->where('id', $cancelled)->update(['status' => 'CANCELLED']);

        $this->assertSame(1, $this->analytics->eventAttendance($this->eventId)['expected']);
    }

    public function test_an_event_with_no_attendees_reports_no_rate_rather_than_zero(): void
    {
        $stats = $this->analytics->eventAttendance($this->eventId);

        $this->assertSame(0, $stats['expected']);
        $this->assertNull(
            $stats['attendance_rate'],
            'A rate of zero would read as nobody came, not as nobody was expected.'
        );
    }

    /**
     * A second check-in on the same list is impossible — a unique index prevents it. The
     * case that can duplicate is one attendee checked in on two lists, which is normal at an
     * event with a main door and a VIP entrance.
     */
    public function test_being_checked_in_on_two_lists_counts_as_one_arrival(): void
    {
        $attendeeId = $this->makeAttendee();
        $secondList = $this->makeCheckInList(isSystemDefault: false);

        $this->checkIn($attendeeId);
        $this->checkIn($attendeeId, listId: $secondList);

        $this->assertSame(
            2,
            DB::table('attendee_check_ins')->where('attendee_id', $attendeeId)->count()
        );
        $this->assertSame(
            1,
            $this->analytics->eventAttendance($this->eventId)['arrived'],
            'Attendance counts people, not scans.'
        );
    }

    // ---------------------------------------------------------------- arrival curve

    public function test_the_arrival_curve_buckets_by_hour(): void
    {
        $first = $this->makeAttendee();
        $second = $this->makeAttendee();
        $later = $this->makeAttendee();

        $this->checkIn($first, Carbon::parse('2026-11-01 09:10:00'));
        $this->checkIn($second, Carbon::parse('2026-11-01 09:50:00'));
        $this->checkIn($later, Carbon::parse('2026-11-01 11:05:00'));

        $curve = $this->analytics->arrivalCurve($this->eventId);

        $this->assertCount(2, $curve);
        $this->assertSame(2, $curve[0]['arrivals']);
        $this->assertSame(1, $curve[1]['arrivals']);
    }

    public function test_the_arrival_curve_uses_the_event_timezone(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['timezone' => 'Asia/Qatar']);

        $attendeeId = $this->makeAttendee();
        // 22:30 UTC is 01:30 the next day in Doha.
        $this->checkIn($attendeeId, Carbon::parse('2026-11-01 22:30:00'));

        $curve = $this->analytics->arrivalCurve($this->eventId);

        $this->assertStringContainsString(
            '2026-11-02 01:00',
            $curve[0]['hour'],
            'A UTC bucket would put a Doha rush in the wrong day and mislead a staffing review.'
        );
    }

    public function test_the_peak_arrival_hour_is_the_busiest(): void
    {
        foreach ([1, 2, 3] as $ignored) {
            $this->checkIn($this->makeAttendee(), Carbon::parse('2026-11-01 09:30:00'));
        }
        $this->checkIn($this->makeAttendee(), Carbon::parse('2026-11-01 14:30:00'));

        $peak = $this->analytics->peakArrivalHour($this->eventId);

        $this->assertSame(3, $peak['arrivals']);
        $this->assertStringContainsString('09:00', $peak['hour']);
    }

    public function test_no_arrivals_means_no_peak(): void
    {
        $this->assertNull($this->analytics->peakArrivalHour($this->eventId));
    }

    // ---------------------------------------------------------------- dwell

    public function test_dwell_is_not_measurable_without_an_exit_point(): void
    {
        $zoneId = $this->makeZone();
        $this->makeAccessPoint($zoneId, 'ENTRY');

        $dwell = $this->analytics->zoneDwellTime($zoneId, $this->eventId);

        $this->assertFalse($dwell->measurable);
        $this->assertFalse($dwell->hasData());
        $this->assertNull(
            $dwell->averageSeconds,
            'An average derived from entries alone is a missing measurement, not a long stay.'
        );
        $this->assertStringContainsString('no exit access point', (string) $dwell->reason);
    }

    public function test_dwell_is_measurable_through_a_bidirectional_point(): void
    {
        $zoneId = $this->makeZone();
        $this->makeAccessPoint($zoneId, 'BIDIRECTIONAL');

        $credentialId = $this->makeCredential();
        $this->log($credentialId, $zoneId, AccessDirection::ENTRY, Carbon::parse('2026-11-01 10:00:00'));
        $this->log($credentialId, $zoneId, AccessDirection::EXIT, Carbon::parse('2026-11-01 10:30:00'));

        $dwell = $this->analytics->zoneDwellTime($zoneId, $this->eventId);

        $this->assertTrue($dwell->measurable);
        $this->assertSame(1, $dwell->sampleSize);
        $this->assertSame(1800, $dwell->averageSeconds);
    }

    public function test_dwell_reports_an_empty_sample_distinctly_from_being_unmeasurable(): void
    {
        $zoneId = $this->makeZone();
        $this->makeAccessPoint($zoneId, 'EXIT');

        $dwell = $this->analytics->zoneDwellTime($zoneId, $this->eventId);

        $this->assertTrue($dwell->measurable, 'The zone can measure dwell; nobody has completed a visit.');
        $this->assertSame(0, $dwell->sampleSize);
        $this->assertFalse($dwell->hasData());
    }

    public function test_a_visitor_who_never_left_does_not_contribute_a_duration(): void
    {
        $zoneId = $this->makeZone();
        $this->makeAccessPoint($zoneId, 'BIDIRECTIONAL');

        $completed = $this->makeCredential();
        $this->log($completed, $zoneId, AccessDirection::ENTRY, Carbon::parse('2026-11-01 10:00:00'));
        $this->log($completed, $zoneId, AccessDirection::EXIT, Carbon::parse('2026-11-01 10:20:00'));

        $stillInside = $this->makeCredential();
        $this->log($stillInside, $zoneId, AccessDirection::ENTRY, Carbon::parse('2026-11-01 10:05:00'));

        $dwell = $this->analytics->zoneDwellTime($zoneId, $this->eventId);

        $this->assertSame(1, $dwell->sampleSize);
        $this->assertSame(1200, $dwell->averageSeconds);
    }

    public function test_dwell_reports_a_median_alongside_the_average(): void
    {
        $zoneId = $this->makeZone();
        $this->makeAccessPoint($zoneId, 'BIDIRECTIONAL');

        foreach ([[0, 600], [0, 600], [0, 6000]] as $index => [$start, $length]) {
            $credentialId = $this->makeCredential();
            $entry = Carbon::parse('2026-11-01 10:00:00')->addMinutes($index * 5);
            $this->log($credentialId, $zoneId, AccessDirection::ENTRY, $entry);
            $this->log($credentialId, $zoneId, AccessDirection::EXIT, $entry->copy()->addSeconds($length));
        }

        $dwell = $this->analytics->zoneDwellTime($zoneId, $this->eventId);

        $this->assertSame(3, $dwell->sampleSize);
        $this->assertSame(600, $dwell->medianSeconds);
        $this->assertGreaterThan(
            $dwell->medianSeconds,
            $dwell->averageSeconds,
            'One long stay drags the mean; the median is why both are reported.'
        );
    }

    public function test_a_denied_scan_does_not_count_toward_dwell(): void
    {
        $zoneId = $this->makeZone();
        $this->makeAccessPoint($zoneId, 'BIDIRECTIONAL');

        $credentialId = $this->makeCredential();
        $this->log($credentialId, $zoneId, AccessDirection::ENTRY, Carbon::parse('2026-11-01 10:00:00'), AccessResult::DENIED_NO_GRANT);
        $this->log($credentialId, $zoneId, AccessDirection::EXIT, Carbon::parse('2026-11-01 10:30:00'), AccessResult::DENIED_NO_GRANT);

        $this->assertSame(0, $this->analytics->zoneDwellTime($zoneId, $this->eventId)->sampleSize);
    }

    // ---------------------------------------------------------------- sessions

    public function test_session_attendance_reports_no_shows(): void
    {
        $sessionId = $this->makeSession(capacity: 100);

        $attended = $this->makeAttendee();
        $absent = $this->makeAttendee();

        $this->registerForSession($sessionId, $attended);
        $this->registerForSession($sessionId, $absent);
        $this->recordSessionAttendance($sessionId, $attended);

        $stats = $this->analytics->sessionAttendance($sessionId);

        $this->assertSame(2, $stats['registered']);
        $this->assertSame(1, $stats['attended']);
        $this->assertSame(1, $stats['no_shows']);
        $this->assertSame(0.5, $stats['no_show_rate']);
        $this->assertSame(0.01, $stats['utilisation']);
    }

    public function test_more_attended_than_registered_is_reported_as_walk_ins(): void
    {
        $sessionId = $this->makeSession(capacity: 50);

        $registered = $this->makeAttendee();
        $walkIn = $this->makeAttendee();

        $this->registerForSession($sessionId, $registered);
        $this->recordSessionAttendance($sessionId, $registered);
        $this->recordSessionAttendance($sessionId, $walkIn);

        $stats = $this->analytics->sessionAttendance($sessionId);

        $this->assertSame(1, $stats['walk_ins']);
        $this->assertSame(
            0,
            $stats['no_shows'],
            'A popular session must not report negative no-shows.'
        );
    }

    public function test_a_session_without_capacity_reports_no_utilisation(): void
    {
        $sessionId = $this->makeSession(capacity: null);

        $this->assertNull($this->analytics->sessionAttendance($sessionId)['utilisation']);
    }

    public function test_sessions_are_ranked_by_unused_registrations(): void
    {
        $worst = $this->makeSession(capacity: 200, title: 'Overbooked keynote');
        $better = $this->makeSession(capacity: 50, title: 'Workshop');

        foreach (range(1, 4) as $ignored) {
            $this->registerForSession($worst, $this->makeAttendee());
        }

        $attendee = $this->makeAttendee();
        $this->registerForSession($better, $attendee);
        $this->recordSessionAttendance($better, $attendee);

        $ranking = $this->analytics->sessionNoShowRanking($this->eventId);

        $this->assertSame('Overbooked keynote', $ranking[0]['title']);
        $this->assertSame(4, $ranking[0]['no_shows']);
    }

    public function test_sessions_with_no_registrations_are_excluded_from_the_ranking(): void
    {
        $this->makeSession(capacity: 10, title: 'Nobody registered');

        $this->assertSame([], $this->analytics->sessionNoShowRanking($this->eventId));
    }

    // ---------------------------------------------------------------- demographics

    public function test_demographics_suppress_small_buckets(): void
    {
        foreach (range(1, 4) as $ignored) {
            $this->makeCredential(nationality: 'QA', company: 'Big Co');
        }
        $this->makeCredential(nationality: 'BH', company: 'Tiny Co');

        $demographics = $this->analytics->demographics($this->eventId, suppressionThreshold: 3);

        $nationalities = collect($demographics['by_nationality']);

        $this->assertSame(4, $nationalities->firstWhere('bucket', 'QA')['total']);
        $this->assertNull(
            $nationalities->firstWhere('bucket', 'BH'),
            'One attendee from one country is not aggregate data.'
        );
    }

    public function test_suppressed_counts_are_still_reported_as_a_total(): void
    {
        foreach (range(1, 4) as $ignored) {
            $this->makeCredential(nationality: 'QA');
        }
        $this->makeCredential(nationality: 'BH');
        $this->makeCredential(nationality: 'OM');

        $demographics = $this->analytics->demographics($this->eventId, suppressionThreshold: 3);
        $other = collect($demographics['by_nationality'])->last();

        $this->assertSame(
            2,
            $other['total'],
            'Dropping them would make the chart disagree with the roster.'
        );
    }

    // ---------------------------------------------------------------- fixtures

    private function log(
        int $credentialId,
        int $zoneId,
        AccessDirection $direction,
        Carbon $occurredAt,
        AccessResult $result = AccessResult::GRANTED,
    ): void {
        DB::table('access_logs')->insert([
            'short_id' => 'al_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'credential_id' => $credentialId,
            'zone_id' => $zoneId,
            'occurred_at' => $occurredAt,
            'recorded_at' => $occurredAt,
            'direction' => $direction->value,
            'result' => $result->value,
            'identifier_type' => 'QR',
            'source' => 'SCAN',
            'is_offline_replay' => false,
            'created_at' => now(),
        ]);
    }

    private function checkIn(int $attendeeId, ?Carbon $at = null, ?int $listId = null): void
    {
        DB::table('attendee_check_ins')->insert([
            'short_id' => 'ci_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'attendee_id' => $attendeeId,
            'order_id' => $this->orderId,
            'product_id' => $this->productId,
            'check_in_list_id' => $listId ?? $this->checkInListId,
            'ip_address' => '127.0.0.1',
            'created_at' => $at ?? now(),
            'updated_at' => $at ?? now(),
        ]);
    }

    private function registerForSession(int $sessionId, int $attendeeId): void
    {
        DB::table('session_registrations')->insert([
            'short_id' => 'sr_'.Str::lower(Str::random(20)),
            'session_id' => $sessionId,
            'attendee_id' => $attendeeId,
            'status' => 'REGISTERED',
            'registered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordSessionAttendance(int $sessionId, int $attendeeId): void
    {
        DB::table('session_attendance')->insert([
            'short_id' => 'sa_'.Str::lower(Str::random(20)),
            'session_id' => $sessionId,
            'attendee_id' => $attendeeId,
            'scanned_at' => now(),
            'direction' => AccessDirection::ENTRY->value,
            'source' => 'SCAN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeSession(?int $capacity, string $title = 'Session'): int
    {
        return (int) DB::table('sessions')->insertGetId([
            'short_id' => 'ss_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'title' => $title,
            'session_type' => 'TALK',
            'status' => 'SCHEDULED',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'timezone' => 'UTC',
            'capacity' => $capacity,
            'requires_registration' => true,
            'allow_waitlist' => false,
            'check_in_enabled' => true,
            'is_published' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeCredential(?string $nationality = null, ?string $company = null): int
    {
        $personId = (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Analytics',
            'last_name' => 'Person',
            'nationality' => $nationality,
            'company' => $company,
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $identifier = app(CredentialIdentifierService::class)->generate();

        return (int) DB::table('credentials')->insertGetId([
            'short_id' => 'cr_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'person_id' => $personId,
            'accreditation_id' => $this->makeAccreditation($personId),
            'credential_type' => 'ATTENDEE',
            'status' => 'ACTIVE',
            'identifier' => $identifier,
            'identifier_hash' => app(CredentialIdentifierService::class)->hash($identifier),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAccreditation(int $personId): int
    {
        $typeId = (int) DB::table('accreditation_types')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'code' => 'VIS'.Str::upper(Str::random(5)),
            'name' => 'Visitor',
            'requires_approval' => false,
            'requires_photo' => false,
            'requires_id_document' => false,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('accreditations')->insertGetId([
            'short_id' => 'ac_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'person_id' => $personId,
            'accreditation_type_id' => $typeId,
            'status' => 'APPROVED',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAccessPoint(int $zoneId, string $direction): int
    {
        return (int) DB::table('access_points')->insertGetId([
            'short_id' => 'ap_'.Str::lower(Str::random(20)),
            'zone_id' => $zoneId,
            'name' => 'Door '.$direction,
            'code' => 'D'.Str::upper(Str::random(6)),
            'direction' => $direction,
            'access_point_type' => 'DOOR',
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
            'name' => 'Hall',
            'code' => 'H'.Str::upper(Str::random(6)),
            'zone_type' => 'GENERAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Only one system-default list per event, which a partial unique index enforces, so an
     * additional list is an ordinary one.
     */
    private function makeCheckInList(bool $isSystemDefault = true): int
    {
        return (int) DB::table('check_in_lists')->insertGetId([
            'short_id' => 'cl_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'name' => $isSystemDefault ? 'Default' : 'VIP entrance',
            'is_system_default' => $isSystemDefault,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAttendee(): int
    {
        return (int) DB::table('attendees')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(16)),
            'public_id' => 'A-'.Str::upper(Str::random(10)),
            'first_name' => 'Analytics',
            'last_name' => 'Attendee',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'order_id' => $this->orderId,
            'product_id' => $this->productId,
            'product_price_id' => $this->productPriceId,
            'event_id' => $this->eventId,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeOrderScaffolding(): void
    {
        $this->productId = (int) DB::table('products')->insertGetId([
            'title' => 'Analytics Ticket',
            'event_id' => $this->eventId,
            'type' => 'FREE',
            'product_type' => 'TICKET',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productPriceId = (int) DB::table('product_prices')->insertGetId([
            'product_id' => $this->productId,
            'price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->orderId = (int) DB::table('orders')->insertGetId([
            'short_id' => 'or_'.Str::lower(Str::random(16)),
            'public_id' => 'O-'.Str::upper(Str::random(10)),
            'event_id' => $this->eventId,
            'status' => 'COMPLETED',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'first_name' => 'Analytics',
            'last_name' => 'Buyer',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeVenue(): int
    {
        return (int) DB::table('venues')->insertGetId([
            'short_id' => 'vn_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Analytics Venue',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Analytics Organizer',
            'email' => 'an-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Analytics Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(2),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
