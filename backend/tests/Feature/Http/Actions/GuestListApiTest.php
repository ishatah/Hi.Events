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

class GuestListApiTest extends TestCase
{
    use AuthenticatesApiRequests;
    use DatabaseTransactions;

    private string $token;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $foreignEventId;

    protected function setUp(): void
    {
        parent::setUp();

        app(RoleSeedService::class)->seedSystemRoles();

        [$this->token, $this->accountId, $this->userId] = $this->makeTenant();
        $this->eventId = $this->makeEvent($this->accountId, $this->userId);

        [, $foreignAccountId, $foreignUserId] = $this->makeTenant();
        $this->foreignEventId = $this->makeEvent($foreignAccountId, $foreignUserId);
    }

    public function test_the_full_invite_and_respond_journey_over_http(): void
    {
        $token = $this->invite(['first_name' => 'Layla', 'email' => 'layla@api.test', 'max_party_size' => 4]);

        $this->postJson("/public/rsvp/{$token}", ['response' => 'ATTENDING', 'party_size' => 3])
            ->assertOk()
            ->assertJsonPath('response', 'ATTENDING')
            ->assertJsonPath('partySize', 3);

        $this->getJson("/events/{$this->eventId}/invitations", $this->authHeaders($this->token))
            ->assertOk()
            ->assertJsonPath('data.0.status', 'ATTENDING')
            ->assertJsonPath('data.0.party_size', 3)
            ->assertJsonPath('summary.attending', 1)
            ->assertJsonPath('summary.expected_guests', 3);
    }

    public function test_answering_needs_no_account(): void
    {
        $token = $this->invite(['first_name' => 'Omar', 'email' => 'omar@api.test']);

        $this->postJson("/public/rsvp/{$token}", ['response' => 'NOT_ATTENDING'])
            ->assertOk()
            ->assertJsonPath('partySize', 0);
    }

    public function test_the_guest_list_never_returns_a_token(): void
    {
        $this->invite(['first_name' => 'Layla', 'email' => 'layla@api.test']);

        $response = $this->getJson("/events/{$this->eventId}/invitations", $this->authHeaders($this->token));

        $response->assertOk();
        $this->assertStringNotContainsString(
            'token',
            $response->getContent(),
            'The guest list is read by staff; handing out the tokens would let any of them answer for a guest.'
        );
    }

    public function test_revoking_returns_no_content_rather_than_failing_after_the_work(): void
    {
        $token = $this->invite(['first_name' => 'Nadia', 'email' => 'nadia@api.test']);
        $invitationId = (int) DB::table('invitations')->where('email', 'nadia@api.test')->value('id');

        // The service and the response are separate failure modes: an action whose declared
        // return type rejects a 204 revokes the invitation and then returns a 500, so the
        // caller sees a failure for work that actually happened.
        $this->deleteJson(
            "/events/{$this->eventId}/invitations/{$invitationId}",
            [],
            $this->authHeaders($this->token)
        )->assertNoContent();

        $this->postJson("/public/rsvp/{$token}", ['response' => 'ATTENDING'])->assertStatus(422);
    }

    public function test_an_over_quota_answer_is_a_validation_error(): void
    {
        $token = $this->invite(['first_name' => 'Layla', 'email' => 'layla@api.test', 'max_party_size' => 2]);

        $this->postJson("/public/rsvp/{$token}", ['response' => 'ATTENDING', 'party_size' => 9])
            ->assertStatus(422)
            ->assertJsonValidationErrors('response');
    }

    public function test_an_unknown_response_is_rejected_by_validation(): void
    {
        $token = $this->invite(['first_name' => 'Layla', 'email' => 'layla@api.test']);

        $this->postJson("/public/rsvp/{$token}", ['response' => 'MAYBE'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('response');
    }

    public function test_an_unknown_token_does_not_reveal_whether_it_existed(): void
    {
        $this->postJson('/public/rsvp/'.Str::random(48), ['response' => 'ATTENDING'])
            ->assertStatus(422);
    }

    public function test_a_duplicate_email_is_a_validation_error(): void
    {
        $this->invite(['first_name' => 'Layla', 'email' => 'layla@api.test']);

        $this->postJson(
            "/events/{$this->eventId}/invitations",
            ['first_name' => 'Layla', 'email' => 'LAYLA@api.test'],
            $this->authHeaders($this->token)
        )->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_the_guest_list_requires_authentication(): void
    {
        $this->getJson("/events/{$this->eventId}/invitations")->assertStatus(401);
    }

    public function test_the_guest_list_endpoints_refuse_a_foreign_event(): void
    {
        $paths = [
            ['GET', "/events/{$this->foreignEventId}/invitations"],
            ['POST', "/events/{$this->foreignEventId}/invitations"],
            ['DELETE', "/events/{$this->foreignEventId}/invitations/1"],
        ];

        foreach ($paths as [$method, $path]) {
            $response = $this->json(
                $method,
                $path,
                ['first_name' => 'Intruder', 'email' => 'intruder@api.test'],
                $this->authHeaders($this->token)
            );

            $this->assertContains(
                $response->getStatusCode(),
                [401, 403, 404],
                sprintf('CROSS-TENANT LEAK: %s %s returned %d.', $method, $path, $response->getStatusCode())
            );
        }
    }

    public function test_a_role_without_the_guest_list_permission_is_refused(): void
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

        $this->getJson("/events/{$this->eventId}/invitations", $this->authHeaders($operatorToken))
            ->assertStatus(403);
    }

    public function test_lead_scores_are_served_over_http(): void
    {
        $exhibitorId = $this->makeExhibitor();
        $this->makeLead($exhibitorId);

        $this->getJson(
            "/events/{$this->eventId}/exhibitors/{$exhibitorId}/lead-scores",
            $this->authHeaders($this->token)
        )->assertOk()
            ->assertJsonPath('data.0.lead_id', fn ($id): bool => is_int($id))
            ->assertJsonStructure(['data' => [['lead_id', 'score', 'band', 'breakdown']]]);
    }

    // ---------------------------------------------------------------- fixtures

    /**
     * @param  array<string, mixed>  $payload
     */
    private function invite(array $payload): string
    {
        $response = $this->postJson(
            "/events/{$this->eventId}/invitations",
            $payload,
            $this->authHeaders($this->token)
        );

        $response->assertStatus(201)->assertJsonStructure(['invitation_id', 'token']);

        return (string) $response->json('token');
    }

    private function makeLead(int $exhibitorId): int
    {
        $personId = (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Api',
            'last_name' => 'Lead',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('leads')->insertGetId([
            'short_id' => 'ld_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'event_exhibitor_id' => $exhibitorId,
            'person_id' => $personId,
            'shared_fields' => json_encode(['first_name' => 'Api', 'company' => 'Buyer Co']),
            'first_captured_at' => now(),
            'last_captured_at' => now(),
            'capture_count' => 2,
            'rating' => 'HOT',
            'status' => 'NEW',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeExhibitor(): int
    {
        $companyId = (int) DB::table('companies')->insertGetId([
            'short_id' => 'co_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Api Exhibitor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('event_exhibitors')->insertGetId([
            'short_id' => 'ee_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'company_id' => $companyId,
            'status' => 'ACTIVE',
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
            'name' => 'Guest List Organizer',
            'email' => 'gapi-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Guest List Event',
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'start_date' => now()->addDays(10),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
