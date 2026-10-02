<?php

namespace Tests\Feature\Services\Domain\Raffle;

use Carbon\CarbonImmutable;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Raffle\RaffleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RaffleServiceTest extends TestCase
{
    use DatabaseTransactions;

    private RaffleService $service;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $venueId;

    private int $zoneId;

    private int $otherZoneId;

    private CarbonImmutable $windowStart;

    private CarbonImmutable $windowEnd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RaffleService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent();
        $this->venueId = $this->makeVenue();
        $this->zoneId = $this->makeZone('Main Hall');
        $this->otherZoneId = $this->makeZone('Side Room');

        $this->windowStart = CarbonImmutable::parse('2026-10-05 09:00:00', 'UTC');
        $this->windowEnd = CarbonImmutable::parse('2026-10-05 17:00:00', 'UTC');
    }

    // ---------------------------------------------------------------- eligibility

    public function test_the_pool_is_the_people_who_actually_entered(): void
    {
        $attended = $this->personWhoEntered();
        $this->personWithATicketWhoStayedHome();

        $raffleId = $this->makeRaffle();

        $this->assertSame(
            1,
            $this->service->poolSummary($raffleId)['eligible'],
            'Being sold a ticket is not the same as turning up; a raffle drawn from ticket '
            .'holders would award prizes to people who stayed home.'
        );

        $draw = $this->service->draw($raffleId);

        $this->assertSame([$attended], $draw->winnerPersonIds);
    }

    public function test_an_entry_outside_the_window_is_not_eligible(): void
    {
        $this->personWhoEntered(at: $this->windowStart->subHours(3));

        $this->assertSame(0, $this->service->poolSummary($this->makeRaffle())['eligible']);
    }

    public function test_a_denied_scan_is_not_an_entry(): void
    {
        $this->personWhoEntered(result: 'DENIED_NO_GRANT');

        $this->assertSame(
            0,
            $this->service->poolSummary($this->makeRaffle())['eligible'],
            'Somebody turned away at the door did not attend.'
        );
    }

    public function test_an_overridden_entry_still_counts_as_attending(): void
    {
        $this->personWhoEntered(result: 'GRANTED_OVERRIDE');

        $this->assertSame(
            1,
            $this->service->poolSummary($this->makeRaffle())['eligible'],
            'Somebody a supervisor waved through is in the room.'
        );
    }

    public function test_an_exit_is_not_an_entry(): void
    {
        $this->personWhoEntered(direction: 'EXIT');

        $this->assertSame(0, $this->service->poolSummary($this->makeRaffle())['eligible']);
    }

    public function test_entering_twice_does_not_double_the_odds(): void
    {
        $personId = $this->makePerson();
        $this->scan($personId, $this->windowStart->addHour());
        $this->scan($personId, $this->windowStart->addHours(4));

        $this->assertSame(
            1,
            $this->service->poolSummary($this->makeRaffle())['eligible'],
            'Stepping out for a coffee should not improve your chances.'
        );
    }

    public function test_a_zone_scoped_raffle_only_draws_from_that_zone(): void
    {
        $inZone = $this->personWhoEntered();
        $this->personWhoEntered(zoneId: $this->otherZoneId);

        $raffleId = $this->makeRaffle(zoneId: $this->zoneId);

        $this->assertSame(1, $this->service->poolSummary($raffleId)['eligible']);
        $this->assertSame([$inZone], $this->service->draw($raffleId)->winnerPersonIds);
    }

    public function test_exhibitor_staff_are_excluded_by_default(): void
    {
        $visitor = $this->personWhoEntered();
        $staffPersonId = $this->personWhoEntered();
        $this->makeExhibitorStaff($staffPersonId);

        $raffleId = $this->makeRaffle();
        $summary = $this->service->poolSummary($raffleId);

        $this->assertSame(1, $summary['eligible']);
        $this->assertSame(1, $summary['excluded_exhibitors']);
        $this->assertSame([$visitor], $this->service->draw($raffleId)->winnerPersonIds);
    }

    public function test_staff_holding_an_accreditation_are_excluded_by_default(): void
    {
        $visitor = $this->personWhoEntered();
        $staffPersonId = $this->personWhoEntered();
        $this->makeStaffCredential($staffPersonId);

        $raffleId = $this->makeRaffle();
        $summary = $this->service->poolSummary($raffleId);

        $this->assertSame(1, $summary['eligible']);
        $this->assertSame(1, $summary['excluded_staff']);
        $this->assertSame([$visitor], $this->service->draw($raffleId)->winnerPersonIds);
    }

    public function test_exclusions_can_be_turned_off(): void
    {
        $this->personWhoEntered();
        $this->makeExhibitorStaff($this->personWhoEntered());

        $raffleId = $this->makeRaffle(excludeStaff: false, excludeExhibitors: false);

        $this->assertSame(2, $this->service->poolSummary($raffleId)['eligible']);
    }

    // ---------------------------------------------------------------- drawing

    public function test_a_draw_records_everything_needed_to_reproduce_it(): void
    {
        foreach (range(1, 20) as $ignored) {
            $this->personWhoEntered();
        }

        $raffleId = $this->makeRaffle(winnerCount: 3);
        $draw = $this->service->draw($raffleId, drawnByUserId: $this->userId);

        $row = DB::table('raffle_draws')->where('id', $draw->drawId)->first();

        $this->assertSame(20, (int) $row->eligible_pool_size);
        $this->assertSame($this->userId, (int) $row->drawn_by_user_id);
        $this->assertNotNull($row->drawn_at);
        $this->assertSame(64, strlen($draw->randomSeed) * 2);
        $this->assertCount(3, $draw->winnerPersonIds);
    }

    public function test_the_same_seed_reproduces_the_same_winners(): void
    {
        foreach (range(1, 30) as $ignored) {
            $this->personWhoEntered();
        }

        $first = $this->service->draw($this->makeRaffle(winnerCount: 3), seed: 'fixed-seed-abc');
        $second = $this->service->draw($this->makeRaffle(winnerCount: 3), seed: 'fixed-seed-abc');

        $this->assertSame(
            $first->winnerPersonIds,
            $second->winnerPersonIds,
            'A contested prize is settled by reproducing the draw, not by asserting the code '
            .'is fair.'
        );
    }

    public function test_a_different_seed_gives_a_different_result(): void
    {
        foreach (range(1, 40) as $ignored) {
            $this->personWhoEntered();
        }

        $first = $this->service->draw($this->makeRaffle(winnerCount: 5), seed: 'seed-one');
        $second = $this->service->draw($this->makeRaffle(winnerCount: 5), seed: 'seed-two');

        $this->assertNotSame($first->winnerPersonIds, $second->winnerPersonIds);
    }

    public function test_a_recorded_draw_verifies_against_its_seed(): void
    {
        foreach (range(1, 25) as $ignored) {
            $this->personWhoEntered();
        }

        $draw = $this->service->draw($this->makeRaffle(winnerCount: 3));

        $verification = $this->service->verify($draw->drawId);

        $this->assertTrue($verification['reproducible']);
        $this->assertTrue($verification['pool_unchanged']);
        $this->assertSame($verification['recorded_winners'], $verification['recomputed_winners']);
    }

    public function test_verification_notices_when_the_pool_has_since_changed(): void
    {
        foreach (range(1, 10) as $ignored) {
            $this->personWhoEntered();
        }

        $draw = $this->service->draw($this->makeRaffle(winnerCount: 2));

        // Somebody entered after the draw but inside the window, which is exactly the kind of
        // late arrival that makes a naive re-run disagree with the record.
        $this->personWhoEntered();

        $verification = $this->service->verify($draw->drawId);

        $this->assertFalse(
            $verification['pool_unchanged'],
            'A re-run that silently drew from a different pool would look like tampering.'
        );
        $this->assertSame(10, $verification['pool_size_at_draw']);
        $this->assertSame(11, $verification['pool_size_now']);
    }

    public function test_no_winners_are_drawn_from_an_empty_pool(): void
    {
        $this->expectExceptionMessageMatches('/Nobody entered/');
        $this->service->draw($this->makeRaffle());
    }

    public function test_a_pool_smaller_than_the_prize_count_draws_everybody_once(): void
    {
        $this->personWhoEntered();
        $this->personWhoEntered();

        $draw = $this->service->draw($this->makeRaffle(winnerCount: 5));

        $this->assertCount(
            2,
            $draw->winnerPersonIds,
            'Announcing five winners when only two exist is worse than announcing two.'
        );
    }

    public function test_nobody_wins_twice_in_one_draw(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->personWhoEntered();
        }

        $winners = $this->service->draw($this->makeRaffle(winnerCount: 5))->winnerPersonIds;

        $this->assertSame(count($winners), count(array_unique($winners)));
    }

    public function test_a_raffle_cannot_be_drawn_twice(): void
    {
        $this->personWhoEntered();
        $raffleId = $this->makeRaffle();

        $this->service->draw($raffleId);

        $this->expectExceptionMessageMatches('/already been drawn/');
        $this->service->draw($raffleId);
    }

    public function test_a_cancelled_raffle_cannot_be_drawn(): void
    {
        $this->personWhoEntered();
        $raffleId = $this->makeRaffle();

        DB::table('raffles')->where('id', $raffleId)->update(['status' => 'CANCELLED']);

        $this->expectExceptionMessageMatches('/was cancelled/');
        $this->service->draw($raffleId);
    }

    public function test_an_unknown_raffle_cannot_be_drawn(): void
    {
        $this->expectException(ResourceConflictException::class);
        $this->service->draw(99999999);
    }

    // ---------------------------------------------------------------- lifecycle

    public function test_a_window_must_end_after_it_starts(): void
    {
        $this->expectExceptionMessageMatches('/must end after it starts/');

        $this->service->create(
            eventId: $this->eventId,
            name: 'Backwards',
            windowStart: $this->windowEnd,
            windowEnd: $this->windowStart,
        );
    }

    public function test_a_raffle_needs_at_least_one_winner(): void
    {
        $this->expectExceptionMessageMatches('/at least one winner/');

        $this->service->create(
            eventId: $this->eventId,
            name: 'No prizes',
            windowStart: $this->windowStart,
            windowEnd: $this->windowEnd,
            winnerCount: 0,
        );
    }

    public function test_a_draft_raffle_can_be_opened(): void
    {
        $raffleId = $this->makeRaffle();

        $this->service->open($raffleId, $this->eventId);

        $this->assertSame('OPEN', DB::table('raffles')->where('id', $raffleId)->value('status'));
    }

    public function test_opening_another_events_raffle_is_refused(): void
    {
        $raffleId = $this->makeRaffle();

        $this->expectExceptionMessageMatches('/could not be opened/');
        $this->service->open($raffleId, $this->makeEvent());
    }

    public function test_a_drawn_raffle_cannot_be_reopened(): void
    {
        $this->personWhoEntered();
        $raffleId = $this->makeRaffle();
        $this->service->draw($raffleId);

        $this->expectExceptionMessageMatches('/could not be opened/');
        $this->service->open($raffleId, $this->eventId);
    }

    public function test_a_claim_is_recorded_against_the_winner(): void
    {
        $this->personWhoEntered();
        $draw = $this->service->draw($this->makeRaffle());

        $winnerId = (int) DB::table('raffle_winners')->where('raffle_draw_id', $draw->drawId)->value('id');

        $this->service->recordClaim($winnerId, claimed: true);

        $row = DB::table('raffle_winners')->where('id', $winnerId)->first();

        $this->assertSame('CLAIMED', $row->status);
        $this->assertNotNull($row->claimed_at);
    }

    public function test_an_unclaimed_prize_is_recorded_as_forfeited(): void
    {
        $this->personWhoEntered();
        $draw = $this->service->draw($this->makeRaffle());

        $winnerId = (int) DB::table('raffle_winners')->where('raffle_draw_id', $draw->drawId)->value('id');

        $this->service->recordClaim($winnerId, claimed: false);

        $this->assertSame(
            'FORFEITED',
            DB::table('raffle_winners')->where('id', $winnerId)->value('status')
        );
        $this->assertNull(DB::table('raffle_winners')->where('id', $winnerId)->value('claimed_at'));
    }

    public function test_the_winner_list_carries_names_in_draw_order(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->personWhoEntered();
        }

        $draw = $this->service->draw($this->makeRaffle(winnerCount: 3));

        $winners = $this->service->winners($draw->drawId);

        $this->assertCount(3, $winners);
        $this->assertSame([1, 2, 3], $winners->pluck('position')->map(fn ($p): int => (int) $p)->all());
        $this->assertNotNull($winners[0]->first_name);
    }

    // ---------------------------------------------------------------- fixtures

    private function personWhoEntered(
        ?CarbonImmutable $at = null,
        string $result = 'GRANTED',
        string $direction = 'ENTRY',
        ?int $zoneId = null,
    ): int {
        $personId = $this->makePerson();

        $this->scan($personId, $at ?? $this->windowStart->addHours(2), $result, $direction, $zoneId);

        return $personId;
    }

    private function personWithATicketWhoStayedHome(): int
    {
        return $this->makePerson();
    }

    private function scan(
        int $personId,
        CarbonImmutable $occurredAt,
        string $result = 'GRANTED',
        string $direction = 'ENTRY',
        ?int $zoneId = null,
    ): void {
        DB::table('access_logs')->insert([
            'short_id' => 'al_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'zone_id' => $zoneId ?? $this->zoneId,
            'person_id' => $personId,
            'occurred_at' => $occurredAt,
            'recorded_at' => $occurredAt,
            'direction' => $direction,
            'result' => $result,
            'identifier_hash' => Str::upper(Str::random(12)),
            'identifier_type' => 'QR',
            'source' => 'SCANNER',
            'created_at' => now(),
        ]);
    }

    private function makeRaffle(
        ?int $zoneId = null,
        int $winnerCount = 1,
        bool $excludeStaff = true,
        bool $excludeExhibitors = true,
    ): int {
        return $this->service->create(
            eventId: $this->eventId,
            name: 'Prize Draw '.Str::random(5),
            windowStart: $this->windowStart,
            windowEnd: $this->windowEnd,
            prizeDescription: 'A very nice prize',
            zoneId: $zoneId,
            winnerCount: $winnerCount,
            excludeStaff: $excludeStaff,
            excludeExhibitors: $excludeExhibitors,
        );
    }

    private function makeExhibitorStaff(int $personId): void
    {
        $companyId = (int) DB::table('companies')->insertGetId([
            'short_id' => 'co_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Exhibitor '.Str::random(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exhibitorId = (int) DB::table('event_exhibitors')->insertGetId([
            'short_id' => 'ee_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'company_id' => $companyId,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('exhibitor_staff')->insert([
            'short_id' => 'es_'.Str::lower(Str::random(20)),
            'event_exhibitor_id' => $exhibitorId,
            'person_id' => $personId,
            'role' => 'STAND_STAFF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeStaffCredential(int $personId): void
    {
        $accreditationTypeId = (int) DB::table('accreditation_types')->insertGetId([
            'short_id' => 'ay_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'name' => 'Crew',
            'code' => 'CREW'.Str::upper(Str::random(4)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accreditationId = (int) DB::table('accreditations')->insertGetId([
            'short_id' => 'ac_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'person_id' => $personId,
            'accreditation_type_id' => $accreditationTypeId,
            'status' => 'APPROVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('credentials')->insert([
            'short_id' => 'cr_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'person_id' => $personId,
            'accreditation_id' => $accreditationId,
            'credential_type' => 'STAFF',
            'status' => 'ACTIVE',
            'identifier' => Str::lower(Str::random(40)),
            'identifier_hash' => hash('sha256', Str::random(40)),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePerson(): int
    {
        return (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Entrant',
            'last_name' => Str::upper(Str::random(5)),
            'email' => Str::lower(Str::random(12)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeZone(string $name): int
    {
        return (int) DB::table('zones')->insertGetId([
            'short_id' => 'zn_'.Str::lower(Str::random(20)),
            'venue_id' => $this->venueId,
            'name' => $name,
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
            'name' => 'Raffle Venue',
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
            'name' => 'Raffle Organizer',
            'email' => 'rf-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Raffle Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(3),
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
