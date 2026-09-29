<?php

namespace Tests\Feature\Http\Actions;

use HiEvents\DomainObjects\Enums\SystemRole;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Permission\RoleSeedService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\AuthenticatesApiRequests;
use Tests\TestCase;

class EventUserApiTest extends TestCase
{
    use AuthenticatesApiRequests;
    use DatabaseTransactions;

    private string $token;

    private string $foreignToken;

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

        [$this->foreignToken, $foreignAccountId, $foreignUserId] = $this->makeTenant();
        $this->foreignEventId = $this->makeEvent($foreignAccountId, $foreignUserId);
    }

    public function test_an_event_role_can_be_granted_listed_and_revoked(): void
    {
        $member = $this->makeAccountMember($this->accountId);

        $granted = $this->postJson("/events/{$this->eventId}/users", [
            'user_id' => $member,
            'role' => SystemRole::CHECKIN_OPERATOR->value,
        ], $this->authHeaders($this->token));

        $granted->assertOk();
        $granted->assertJsonPath('data.user_id', $member);

        $list = $this->getJson("/events/{$this->eventId}/users", $this->authHeaders($this->token));
        $list->assertOk();
        $this->assertCount(1, $list->json('data'));

        $this->deleteJson("/events/{$this->eventId}/users/{$member}", [], $this->authHeaders($this->token))
            ->assertSuccessful();

        $after = $this->getJson("/events/{$this->eventId}/users", $this->authHeaders($this->token));
        $this->assertCount(0, $after->json('data'));
    }

    public function test_an_account_level_role_is_rejected(): void
    {
        $member = $this->makeAccountMember($this->accountId);

        $this->postJson("/events/{$this->eventId}/users", [
            'user_id' => $member,
            'role' => SystemRole::ADMIN->value,
        ], $this->authHeaders($this->token))->assertStatus(422);
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $member = $this->makeAccountMember($this->accountId);

        $this->postJson("/events/{$this->eventId}/users", [
            'user_id' => $member,
            'role' => 'NOT_A_ROLE',
        ], $this->authHeaders($this->token))->assertStatus(422);
    }

    public function test_a_past_expiry_is_rejected(): void
    {
        $member = $this->makeAccountMember($this->accountId);

        $this->postJson("/events/{$this->eventId}/users", [
            'user_id' => $member,
            'role' => SystemRole::CHECKIN_OPERATOR->value,
            'expires_at' => now()->subDay()->toIso8601String(),
        ], $this->authHeaders($this->token))->assertStatus(422);
    }

    public function test_the_event_role_endpoints_refuse_a_foreign_event(): void
    {
        $member = $this->makeAccountMember($this->accountId);

        $grant = $this->postJson("/events/{$this->foreignEventId}/users", [
            'user_id' => $member,
            'role' => SystemRole::CHECKIN_OPERATOR->value,
        ], $this->authHeaders($this->token));

        $this->assertContains($grant->getStatusCode(), [401, 403, 404]);

        $list = $this->getJson("/events/{$this->foreignEventId}/users", $this->authHeaders($this->token));
        $this->assertContains($list->getStatusCode(), [401, 403, 404]);

        $revoke = $this->deleteJson(
            "/events/{$this->foreignEventId}/users/{$member}",
            [],
            $this->authHeaders($this->token)
        );
        $this->assertContains($revoke->getStatusCode(), [401, 403, 404]);
    }

    public function test_a_user_from_another_account_cannot_be_granted_a_role(): void
    {
        $stranger = User::factory()->withAccount()->create();

        $this->postJson("/events/{$this->eventId}/users", [
            'user_id' => (int) $stranger->id,
            'role' => SystemRole::CHECKIN_OPERATOR->value,
        ], $this->authHeaders($this->token))->assertStatus(422);
    }

    private function makeAccountMember(int $accountId): int
    {
        $user = User::factory()->create();

        DB::table('account_users')->insert([
            'user_id' => $user->id,
            'account_id' => $accountId,
            'role' => 'VIEWER',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) $user->id;
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
            'name' => 'Event User Organizer',
            'email' => 'eu-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Event User Test Event',
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'start_date' => now()->addDays(10),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'DRAFT',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
