<?php

namespace Tests\Feature\Http\Actions;

use HiEvents\Models\User;
use HiEvents\Services\Domain\Networking\NetworkingService;
use HiEvents\Services\Domain\Permission\RoleSeedService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\AuthenticatesApiRequests;
use Tests\TestCase;

class NetworkingApiTest extends TestCase
{
    use AuthenticatesApiRequests;
    use DatabaseTransactions;

    private string $token;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $foreignEventId;

    private int $layla;

    private int $omar;

    private int $noor;

    protected function setUp(): void
    {
        parent::setUp();

        app(RoleSeedService::class)->seedSystemRoles();

        [$this->token, $this->accountId, $this->userId] = $this->makeTenant();
        $this->eventId = $this->makeEvent($this->accountId, $this->userId);

        [, $foreignAccountId, $foreignUserId] = $this->makeTenant();
        $this->foreignEventId = $this->makeEvent($foreignAccountId, $foreignUserId);

        $networking = app(NetworkingService::class);

        $this->layla = $this->makePerson('Layla');
        $this->omar = $this->makePerson('Omar');
        $this->noor = $this->makePerson('Noor');

        foreach ([$this->layla, $this->omar, $this->noor] as $personId) {
            $networking->optIn($this->eventId, $personId, headline: 'Looking for partners');
        }
    }

    public function test_the_directory_excludes_the_viewer(): void
    {
        $this->getJson(
            "/events/{$this->eventId}/networking/{$this->layla}/directory",
            $this->authHeaders($this->token)
        )->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['first_name' => 'Layla']);
    }

    public function test_the_directory_can_be_searched(): void
    {
        $this->getJson(
            "/events/{$this->eventId}/networking/{$this->layla}/directory?search=omar",
            $this->authHeaders($this->token)
        )->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Omar');
    }

    public function test_a_meeting_is_requested_and_confirmed_over_http(): void
    {
        $meetingId = $this->requestMeeting();

        $this->postJson(
            "/events/{$this->eventId}/meetings/{$meetingId}/respond",
            ['person_id' => $this->omar, 'accept' => true],
            $this->authHeaders($this->token)
        )->assertOk();

        $this->getJson(
            "/events/{$this->eventId}/networking/{$this->omar}/meetings",
            $this->authHeaders($this->token)
        )->assertOk()
            ->assertJsonPath('data.0.status', 'CONFIRMED')
            ->assertJsonPath('data.0.role', 'INVITEE');
    }

    public function test_a_clash_is_reported_at_request_and_refused_at_confirmation(): void
    {
        $first = $this->requestMeeting();

        $this->postJson(
            "/events/{$this->eventId}/meetings/{$first}/respond",
            ['person_id' => $this->omar, 'accept' => true],
            $this->authHeaders($this->token)
        )->assertOk();

        $second = $this->postJson(
            "/events/{$this->eventId}/meetings",
            [
                'requester_person_id' => $this->noor,
                'invitee_person_ids' => [$this->omar],
                'starts_at' => now()->addDay()->setTime(10, 10)->toIso8601String(),
                'ends_at' => now()->addDay()->setTime(10, 40)->toIso8601String(),
            ],
            $this->authHeaders($this->token)
        )->assertStatus(201);

        $second->assertJsonCount(1, 'clashes');

        $this->postJson(
            "/events/{$this->eventId}/meetings/{$second->json('meeting_id')}/respond",
            ['person_id' => $this->omar, 'accept' => true],
            $this->authHeaders($this->token)
        )->assertStatus(422)->assertJsonValidationErrors('accept');
    }

    public function test_a_meeting_can_be_cancelled(): void
    {
        $meetingId = $this->requestMeeting();

        $this->deleteJson(
            "/events/{$this->eventId}/meetings/{$meetingId}",
            [],
            $this->authHeaders($this->token)
        )->assertNoContent();

        $this->assertSame(
            'CANCELLED',
            DB::table('meetings')->where('id', $meetingId)->value('status')
        );
    }

    public function test_a_backwards_meeting_is_rejected_by_validation(): void
    {
        $this->postJson(
            "/events/{$this->eventId}/meetings",
            [
                'requester_person_id' => $this->layla,
                'invitee_person_ids' => [$this->omar],
                'starts_at' => now()->addDay()->setTime(11, 0)->toIso8601String(),
                'ends_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            ],
            $this->authHeaders($this->token)
        )->assertStatus(422)->assertJsonValidationErrors('ends_at');
    }

    public function test_a_meeting_of_another_event_cannot_be_answered_through_this_one(): void
    {
        $meetingId = $this->requestMeeting();

        $response = $this->postJson(
            "/events/{$this->foreignEventId}/meetings/{$meetingId}/respond",
            ['person_id' => $this->omar, 'accept' => true],
            $this->authHeaders($this->token)
        );

        $this->assertContains($response->getStatusCode(), [401, 403, 404, 422]);
        $this->assertSame(
            'REQUESTED',
            DB::table('meetings')->where('id', $meetingId)->value('status')
        );
    }

    public function test_the_networking_endpoints_refuse_a_foreign_event(): void
    {
        $paths = [
            ['GET', "/events/{$this->foreignEventId}/networking/{$this->layla}/directory"],
            ['GET', "/events/{$this->foreignEventId}/networking/{$this->layla}/meetings"],
            ['POST', "/events/{$this->foreignEventId}/meetings"],
            ['DELETE', "/events/{$this->foreignEventId}/meetings/1"],
        ];

        foreach ($paths as [$method, $path]) {
            $response = $this->json($method, $path, [
                'requester_person_id' => $this->layla,
                'invitee_person_ids' => [$this->omar],
                'starts_at' => now()->addDay()->toIso8601String(),
                'ends_at' => now()->addDay()->addHour()->toIso8601String(),
            ], $this->authHeaders($this->token));

            $this->assertContains(
                $response->getStatusCode(),
                [401, 403, 404],
                sprintf('CROSS-TENANT LEAK: %s %s returned %d.', $method, $path, $response->getStatusCode())
            );
        }
    }

    public function test_the_networking_endpoints_require_authentication(): void
    {
        $this->getJson("/events/{$this->eventId}/networking/{$this->layla}/directory")->assertStatus(401);
    }

    public function test_a_role_without_the_networking_permission_is_refused(): void
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

        $this->getJson(
            "/events/{$this->eventId}/networking/{$this->layla}/directory",
            $this->authHeaders($operatorToken)
        )->assertStatus(403);
    }

    // ---------------------------------------------------------------- fixtures

    private function requestMeeting(): int
    {
        return (int) $this->postJson(
            "/events/{$this->eventId}/meetings",
            [
                'requester_person_id' => $this->layla,
                'invitee_person_ids' => [$this->omar],
                'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
                'ends_at' => now()->addDay()->setTime(10, 30)->toIso8601String(),
                'location_label' => 'Table 14',
            ],
            $this->authHeaders($this->token)
        )->assertStatus(201)->json('meeting_id');
    }

    private function makePerson(string $firstName): int
    {
        return (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => $firstName,
            'last_name' => 'Networker',
            'company' => $firstName.' Trading',
            'email' => Str::lower(Str::random(12)).'@test.local',
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
            'name' => 'Networking API Organizer',
            'email' => 'nwapi-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Networking API Event',
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'start_date' => now()->addDays(4),
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
