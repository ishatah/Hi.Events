<?php

namespace Tests\Feature\Http\Actions;

use HiEvents\Models\User;
use HiEvents\Services\Domain\Permission\RoleSeedService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\AuthenticatesApiRequests;
use Tests\TestCase;

class RaffleApiTest extends TestCase
{
    use AuthenticatesApiRequests;
    use DatabaseTransactions;

    private string $token;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $foreignEventId;

    private int $zoneId;

    protected function setUp(): void
    {
        parent::setUp();

        app(RoleSeedService::class)->seedSystemRoles();

        [$this->token, $this->accountId, $this->userId] = $this->makeTenant();
        $this->eventId = $this->makeEvent($this->accountId, $this->userId);
        $this->zoneId = $this->makeZone($this->eventId);

        [, $foreignAccountId, $foreignUserId] = $this->makeTenant();
        $this->foreignEventId = $this->makeEvent($foreignAccountId, $foreignUserId);
    }

    public function test_the_full_raffle_journey_over_http(): void
    {
        foreach (range(1, 20) as $ignored) {
            $this->entrant();
        }

        $created = $this->postJson(
            "/events/{$this->eventId}/raffles",
            [
                'name' => 'Grand Prize',
                'prize_description' => 'A car',
                'eligibility_window_start' => now()->subHours(4)->toIso8601String(),
                'eligibility_window_end' => now()->subHour()->toIso8601String(),
                'winner_count' => 3,
            ],
            $this->authHeaders($this->token)
        )->assertStatus(201);

        $raffleId = $created->json('id');

        $created->assertJsonPath('pool.eligible', 20);

        $drawn = $this->postJson(
            "/events/{$this->eventId}/raffles/{$raffleId}/draw",
            [],
            $this->authHeaders($this->token)
        )->assertStatus(201);

        $drawn->assertJsonPath('draw.eligiblePoolSize', 20);
        $drawn->assertJsonCount(3, 'winners');

        $drawId = $drawn->json('draw.drawId');

        $this->getJson(
            "/events/{$this->eventId}/raffles/{$raffleId}/draws/{$drawId}/verify",
            $this->authHeaders($this->token)
        )->assertOk()
            ->assertJsonPath('reproducible', true)
            ->assertJsonPath('pool_unchanged', true);
    }

    public function test_the_draw_records_who_ran_it(): void
    {
        $this->entrant();

        $raffleId = $this->createRaffle();

        $drawId = $this->postJson(
            "/events/{$this->eventId}/raffles/{$raffleId}/draw",
            [],
            $this->authHeaders($this->token)
        )->assertStatus(201)->json('draw.drawId');

        $this->assertSame(
            $this->userId,
            (int) DB::table('raffle_draws')->where('id', $drawId)->value('drawn_by_user_id'),
            'A contested prize needs to show who pressed the button.'
        );
    }

    public function test_the_pool_can_be_checked_before_drawing(): void
    {
        $this->entrant();
        $this->entrant();

        $raffleId = $this->createRaffle();

        $this->getJson(
            "/events/{$this->eventId}/raffles/{$raffleId}/pool",
            $this->authHeaders($this->token)
        )->assertOk()->assertJsonPath('eligible', 2);

        $this->assertSame(
            'DRAFT',
            DB::table('raffles')->where('id', $raffleId)->value('status'),
            'Checking who is eligible must not draw.'
        );
    }

    public function test_drawing_an_empty_pool_is_a_validation_error(): void
    {
        $raffleId = $this->createRaffle();

        $this->postJson(
            "/events/{$this->eventId}/raffles/{$raffleId}/draw",
            [],
            $this->authHeaders($this->token)
        )->assertStatus(422)->assertJsonValidationErrors('raffle_id');
    }

    public function test_drawing_twice_is_a_validation_error(): void
    {
        $this->entrant();
        $raffleId = $this->createRaffle();

        $this->postJson("/events/{$this->eventId}/raffles/{$raffleId}/draw", [], $this->authHeaders($this->token))
            ->assertStatus(201);

        $this->postJson("/events/{$this->eventId}/raffles/{$raffleId}/draw", [], $this->authHeaders($this->token))
            ->assertStatus(422);
    }

    public function test_a_backwards_window_is_rejected_by_validation(): void
    {
        $this->postJson(
            "/events/{$this->eventId}/raffles",
            [
                'name' => 'Backwards',
                'eligibility_window_start' => now()->toIso8601String(),
                'eligibility_window_end' => now()->subHour()->toIso8601String(),
            ],
            $this->authHeaders($this->token)
        )->assertStatus(422)->assertJsonValidationErrors('eligibility_window_end');
    }

    public function test_a_claim_is_recorded(): void
    {
        $this->entrant();
        $raffleId = $this->createRaffle();

        $drawId = $this->postJson(
            "/events/{$this->eventId}/raffles/{$raffleId}/draw",
            [],
            $this->authHeaders($this->token)
        )->json('draw.drawId');

        $winnerId = (int) DB::table('raffle_winners')->where('raffle_draw_id', $drawId)->value('id');

        $this->postJson(
            "/events/{$this->eventId}/raffles/{$raffleId}/winners/{$winnerId}/claim",
            ['claimed' => true],
            $this->authHeaders($this->token)
        )->assertOk();

        $this->assertSame(
            'CLAIMED',
            DB::table('raffle_winners')->where('id', $winnerId)->value('status')
        );
    }

    public function test_a_raffle_of_another_event_cannot_be_drawn_through_this_one(): void
    {
        $this->entrant();
        $raffleId = $this->createRaffle();

        // The raffle id arrives in the URL while authorization was checked on the event, so
        // the two must be confirmed to belong together.
        $response = $this->postJson(
            "/events/{$this->foreignEventId}/raffles/{$raffleId}/draw",
            [],
            $this->authHeaders($this->token)
        );

        $this->assertContains($response->getStatusCode(), [401, 403, 404, 422]);
        $this->assertSame(
            'DRAFT',
            DB::table('raffles')->where('id', $raffleId)->value('status'),
            'A draw run through the wrong event would spend the raffle.'
        );
    }

    public function test_the_raffle_endpoints_refuse_a_foreign_event(): void
    {
        $paths = [
            ['GET', "/events/{$this->foreignEventId}/raffles"],
            ['POST', "/events/{$this->foreignEventId}/raffles"],
            ['GET', "/events/{$this->foreignEventId}/raffles/1/pool"],
            ['POST', "/events/{$this->foreignEventId}/raffles/1/draw"],
        ];

        foreach ($paths as [$method, $path]) {
            $response = $this->json($method, $path, [
                'name' => 'Intruder',
                'eligibility_window_start' => now()->subHour()->toIso8601String(),
                'eligibility_window_end' => now()->toIso8601String(),
            ], $this->authHeaders($this->token));

            $this->assertContains(
                $response->getStatusCode(),
                [401, 403, 404],
                sprintf('CROSS-TENANT LEAK: %s %s returned %d.', $method, $path, $response->getStatusCode())
            );
        }
    }

    public function test_the_raffle_endpoints_require_authentication(): void
    {
        $this->getJson("/events/{$this->eventId}/raffles")->assertStatus(401);
    }

    public function test_a_role_without_the_raffle_permission_is_refused(): void
    {
        $operator = User::factory()->create();

        DB::table('account_users')->insert([
            'user_id' => $operator->id,
            'account_id' => $this->accountId,
            'role' => 'CHECKIN_OPERATOR',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $operatorToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser($operator);

        $this->getJson("/events/{$this->eventId}/raffles", $this->authHeaders($operatorToken))
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------- fixtures

    private function createRaffle(int $winnerCount = 1): int
    {
        return (int) $this->postJson(
            "/events/{$this->eventId}/raffles",
            [
                'name' => 'Prize '.Str::random(5),
                'eligibility_window_start' => now()->subHours(4)->toIso8601String(),
                'eligibility_window_end' => now()->subHour()->toIso8601String(),
                'winner_count' => $winnerCount,
            ],
            $this->authHeaders($this->token)
        )->assertStatus(201)->json('id');
    }

    private function entrant(): int
    {
        $personId = (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Entrant',
            'last_name' => Str::upper(Str::random(5)),
            'email' => Str::lower(Str::random(12)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('access_logs')->insert([
            'short_id' => 'al_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'zone_id' => $this->zoneId,
            'person_id' => $personId,
            'occurred_at' => now()->subHours(2),
            'recorded_at' => now()->subHours(2),
            'direction' => 'ENTRY',
            'result' => 'GRANTED',
            'raw_identifier' => Str::upper(Str::random(12)),
            'identifier_type' => 'QR',
            'source' => 'SCANNER',
            'created_at' => now(),
        ]);

        return $personId;
    }

    private function makeZone(int $eventId): int
    {
        $venueId = (int) DB::table('venues')->insertGetId([
            'short_id' => 'vn_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Raffle API Venue',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('event_venues')->insert([
            'event_id' => $eventId,
            'venue_id' => $venueId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('zones')->insertGetId([
            'short_id' => 'zn_'.Str::lower(Str::random(20)),
            'venue_id' => $venueId,
            'name' => 'Main Hall',
            'code' => Str::upper(Str::random(8)),
            'zone_type' => 'GENERAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function makeTenant(): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = (int) $user->accounts()->first()->id;
        $token = JWTAuth::claims(['account_id' => $accountId])->fromUser($user);

        return [$token, $accountId, (int) $user->id];
    }

    private function makeEvent(int $accountId, int $userId): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Raffle API Organizer',
            'email' => 'rfapi-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Raffle API Event',
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'start_date' => now()->addDays(2),
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
