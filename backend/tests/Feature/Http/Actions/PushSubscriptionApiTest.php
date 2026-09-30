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

class PushSubscriptionApiTest extends TestCase
{
    use AuthenticatesApiRequests;
    use DatabaseTransactions;

    private string $token;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private string $otherToken;

    private int $otherUserId;

    protected function setUp(): void
    {
        parent::setUp();

        app(RoleSeedService::class)->seedSystemRoles();

        [$this->token, $this->accountId, $this->userId] = $this->makeTenant();
        $this->eventId = $this->makeEvent($this->accountId, $this->userId);

        [$this->otherToken, , $this->otherUserId] = $this->makeTenant();
    }

    public function test_a_web_subscription_is_registered_for_the_calling_user(): void
    {
        $id = $this->postJson('/push-subscriptions', [
            'platform' => 'WEB',
            'endpoint' => 'https://push.example.test/abc',
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-key'],
            'event_id' => $this->eventId,
        ], $this->authHeaders($this->token))->assertStatus(201)->json('id');

        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $id,
            'subscriber_type' => 'USER',
            'subscriber_id' => $this->userId,
            'platform' => 'WEB',
        ]);
    }

    public function test_a_native_subscription_is_registered(): void
    {
        $this->postJson('/push-subscriptions', [
            'platform' => 'FCM',
            'token' => 'fcm-token-abc',
        ], $this->authHeaders($this->token))->assertStatus(201);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscriber_id' => $this->userId,
            'platform' => 'FCM',
            'token' => 'fcm-token-abc',
        ]);
    }

    public function test_a_subscription_cannot_be_registered_for_somebody_else(): void
    {
        // The request carries no subscriber id at all, which is the point: one that did could
        // sign another person's phone up to another person's alerts.
        $id = $this->postJson('/push-subscriptions', [
            'platform' => 'FCM',
            'token' => 'token-for-me',
            'subscriber_id' => $this->otherUserId,
            'subscriber_type' => 'ATTENDEE',
        ], $this->authHeaders($this->token))->assertStatus(201)->json('id');

        $row = DB::table('push_subscriptions')->where('id', $id)->first();

        $this->assertSame($this->userId, (int) $row->subscriber_id);
        $this->assertSame('USER', $row->subscriber_type);
    }

    public function test_a_web_subscription_without_its_keys_is_rejected(): void
    {
        $this->postJson('/push-subscriptions', [
            'platform' => 'WEB',
            'endpoint' => 'https://push.example.test/nokeys',
        ], $this->authHeaders($this->token))
            ->assertStatus(422)
            ->assertJsonValidationErrors('keys.p256dh');
    }

    public function test_a_native_subscription_without_a_token_is_rejected(): void
    {
        $this->postJson('/push-subscriptions', [
            'platform' => 'APNS',
        ], $this->authHeaders($this->token))
            ->assertStatus(422)
            ->assertJsonValidationErrors('token');
    }

    public function test_an_unsupported_platform_is_rejected(): void
    {
        $this->postJson('/push-subscriptions', [
            'platform' => 'BLACKBERRY',
            'token' => 'x',
        ], $this->authHeaders($this->token))
            ->assertStatus(422)
            ->assertJsonValidationErrors('platform');
    }

    public function test_declined_categories_are_stored_on_registration(): void
    {
        $id = $this->postJson('/push-subscriptions', [
            'platform' => 'FCM',
            'token' => 'picky-token',
            'declined_categories' => ['REMINDER'],
        ], $this->authHeaders($this->token))->assertStatus(201)->json('id');

        $categories = json_decode(
            (string) DB::table('push_subscriptions')->where('id', $id)->value('categories'),
            true
        );

        $this->assertSame(['REMINDER'], $categories['declined']);
    }

    public function test_revoking_turns_push_off_across_every_device(): void
    {
        foreach (['token-one', 'token-two'] as $token) {
            $this->postJson('/push-subscriptions', [
                'platform' => 'FCM',
                'token' => $token,
            ], $this->authHeaders($this->token))->assertStatus(201);
        }

        $this->deleteJson('/push-subscriptions', [], $this->authHeaders($this->token))
            ->assertOk()
            ->assertJsonPath('revoked', 2);

        $this->assertSame(
            0,
            DB::table('push_subscriptions')
                ->where('subscriber_id', $this->userId)
                ->whereNull('revoked_at')
                ->count()
        );
    }

    public function test_revoking_does_not_touch_another_users_devices(): void
    {
        $this->postJson('/push-subscriptions', [
            'platform' => 'FCM',
            'token' => 'mine',
        ], $this->authHeaders($this->token))->assertStatus(201);

        $this->postJson('/push-subscriptions', [
            'platform' => 'FCM',
            'token' => 'theirs',
        ], $this->authHeaders($this->otherToken))->assertStatus(201);

        $this->deleteJson('/push-subscriptions', [], $this->authHeaders($this->token))->assertOk();

        $this->assertSame(
            1,
            DB::table('push_subscriptions')
                ->where('subscriber_id', $this->otherUserId)
                ->whereNull('revoked_at')
                ->count(),
            'Silencing somebody else would mean a missed incident alert for them.'
        );
    }

    public function test_push_health_reports_reachable_devices(): void
    {
        $this->postJson('/push-subscriptions', [
            'platform' => 'FCM',
            'token' => 'staff-token',
            'event_id' => $this->eventId,
        ], $this->authHeaders($this->token))->assertStatus(201);

        $this->getJson("/events/{$this->eventId}/push-health", $this->authHeaders($this->token))
            ->assertOk()
            ->assertJsonPath('live', 1)
            ->assertJsonPath('revoked', 0)
            ->assertJsonPath('by_audience.USER.FCM.live', 1);
    }

    public function test_registration_requires_authentication(): void
    {
        $this->postJson('/push-subscriptions', [
            'platform' => 'FCM',
            'token' => 'anonymous',
        ])->assertStatus(401);
    }

    public function test_push_health_requires_authentication(): void
    {
        $this->getJson("/events/{$this->eventId}/push-health")->assertStatus(401);
    }

    public function test_push_health_refuses_a_foreign_event(): void
    {
        [, $foreignAccountId, $foreignUserId] = $this->makeTenant();
        $foreignEventId = $this->makeEvent($foreignAccountId, $foreignUserId);

        $response = $this->getJson(
            "/events/{$foreignEventId}/push-health",
            $this->authHeaders($this->token)
        );

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403, 404],
            sprintf('CROSS-TENANT LEAK: push-health returned %d.', $response->getStatusCode())
        );
    }

    public function test_registering_against_a_foreign_event_is_refused(): void
    {
        [, $foreignAccountId, $foreignUserId] = $this->makeTenant();
        $foreignEventId = $this->makeEvent($foreignAccountId, $foreignUserId);

        $response = $this->postJson('/push-subscriptions', [
            'platform' => 'FCM',
            'token' => 'intruder-token',
            'event_id' => $foreignEventId,
        ], $this->authHeaders($this->token));

        $this->assertContains($response->getStatusCode(), [401, 403, 404]);
        $this->assertDatabaseMissing('push_subscriptions', ['token' => 'intruder-token']);
    }

    // ---------------------------------------------------------------- fixtures

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
            'name' => 'Push API Organizer',
            'email' => 'pushapi-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Push API Event',
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
            'user_id' => $userId,
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
