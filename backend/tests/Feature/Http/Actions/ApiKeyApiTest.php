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

class ApiKeyApiTest extends TestCase
{
    use AuthenticatesApiRequests;
    use DatabaseTransactions;

    private string $token;

    private string $foreignToken;

    private int $accountId;

    private int $userId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        app(RoleSeedService::class)->seedSystemRoles();

        [$this->token, $this->accountId, $this->userId] = $this->makeTenant();
        $this->eventId = $this->makeEvent($this->accountId, $this->userId);

        [$this->foreignToken] = $this->makeTenant();
    }

    public function test_a_key_is_returned_once_and_never_again(): void
    {
        $created = $this->postJson('/api-keys', [
            'name' => 'Integration',
            'scopes' => ['attendee.view'],
        ], $this->authHeaders($this->token));

        $created->assertOk();
        $created->assertJsonStructure(['id', 'key_prefix', 'key', 'message']);

        $plaintext = $created->json('key');

        $listed = $this->getJson('/api-keys', $this->authHeaders($this->token));

        $listed->assertOk();
        $this->assertStringNotContainsString(
            $plaintext,
            $listed->getContent(),
            'A listed key must expose only its prefix.'
        );
    }

    public function test_an_unknown_scope_is_rejected(): void
    {
        $this->postJson('/api-keys', [
            'name' => 'Bogus',
            'scopes' => ['not.a.real.scope'],
        ], $this->authHeaders($this->token))->assertStatus(422);
    }

    public function test_keys_are_not_visible_to_another_account(): void
    {
        $this->postJson('/api-keys', [
            'name' => 'Private',
            'scopes' => ['attendee.view'],
        ], $this->authHeaders($this->token))->assertOk();

        $listed = $this->getJson('/api-keys', $this->authHeaders($this->foreignToken));

        $listed->assertOk();
        $this->assertCount(0, $listed->json('data'));
    }

    public function test_another_account_cannot_revoke_a_key(): void
    {
        $id = $this->postJson('/api-keys', [
            'name' => 'Private',
            'scopes' => ['attendee.view'],
        ], $this->authHeaders($this->token))->json('id');

        $this->deleteJson("/api-keys/{$id}", [], $this->authHeaders($this->foreignToken))
            ->assertStatus(422);

        $this->assertNull(
            DB::table('api_keys')->where('id', $id)->value('revoked_at'),
            'A foreign revoke must not take effect.'
        );
    }

    public function test_the_v1_endpoint_requires_a_key(): void
    {
        $this->getJson("/v1/events/{$this->eventId}/attendees")->assertStatus(401);
    }

    public function test_a_jwt_is_not_accepted_as_an_api_key(): void
    {
        $this->getJson(
            "/v1/events/{$this->eventId}/attendees",
            ['Authorization' => 'Bearer '.$this->token, 'Accept' => 'application/json']
        )->assertStatus(401);
    }

    public function test_a_key_without_the_scope_is_refused(): void
    {
        $key = $this->issueKey(['order.view']);

        $this->getJson("/v1/events/{$this->eventId}/attendees", $this->keyHeaders($key))
            ->assertStatus(403);
    }

    public function test_a_key_with_the_scope_reads_attendees(): void
    {
        $key = $this->issueKey(['attendee.view']);

        $response = $this->getJson("/v1/events/{$this->eventId}/attendees", $this->keyHeaders($key));

        $response->assertOk();
        $response->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total']]);
        $response->assertHeader('X-RateLimit-Limit');
    }

    public function test_a_foreign_event_is_not_found_rather_than_empty(): void
    {
        $key = $this->issueKey(['attendee.view']);

        [, $foreignAccountId, $foreignUserId] = $this->makeTenant();
        $foreignEventId = $this->makeEvent($foreignAccountId, $foreignUserId);

        $this->getJson("/v1/events/{$foreignEventId}/attendees", $this->keyHeaders($key))
            ->assertStatus(404);
    }

    public function test_an_event_scoped_key_cannot_read_another_event(): void
    {
        $otherEventId = $this->makeEvent($this->accountId, $this->userId);
        $key = $this->issueKey(['attendee.view'], $this->eventId);

        $this->getJson("/v1/events/{$this->eventId}/attendees", $this->keyHeaders($key))
            ->assertOk();

        $this->getJson("/v1/events/{$otherEventId}/attendees", $this->keyHeaders($key))
            ->assertStatus(403);
    }

    public function test_a_revoked_key_is_refused(): void
    {
        $created = $this->postJson('/api-keys', [
            'name' => 'Short lived',
            'scopes' => ['attendee.view'],
        ], $this->authHeaders($this->token));

        $key = $created->json('key');

        $this->deleteJson('/api-keys/'.$created->json('id'), [], $this->authHeaders($this->token))
            ->assertSuccessful();

        $this->getJson("/v1/events/{$this->eventId}/attendees", $this->keyHeaders($key))
            ->assertStatus(401);
    }

    /**
     * @param  array<int, string>  $scopes
     */
    private function issueKey(array $scopes, ?int $eventId = null): string
    {
        $payload = ['name' => 'Test key', 'scopes' => $scopes];

        if ($eventId !== null) {
            $payload['event_id'] = $eventId;
        }

        return (string) $this->postJson('/api-keys', $payload, $this->authHeaders($this->token))
            ->json('key');
    }

    /**
     * @return array<string, string>
     */
    private function keyHeaders(string $key): array
    {
        return ['Authorization' => 'Bearer '.$key, 'Accept' => 'application/json'];
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
            'name' => 'API Key API Organizer',
            'email' => 'aka-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'API Key API Event',
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'start_date' => now()->addDays(3),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
