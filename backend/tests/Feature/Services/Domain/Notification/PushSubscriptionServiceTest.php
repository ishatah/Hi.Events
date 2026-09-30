<?php

namespace Tests\Feature\Services\Domain\Notification;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Notification\PushSubscriptionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PushSubscriptionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PushSubscriptionService $service;

    private int $accountId;

    private int $userId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PushSubscriptionService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent();
    }

    // ---------------------------------------------------------------- registration

    public function test_a_web_subscription_stores_its_keys(): void
    {
        $id = $this->service->registerWeb(
            subscriberType: 'USER',
            subscriberId: $this->userId,
            endpoint: 'https://push.example.test/abc123',
            p256dh: 'p256dh-key',
            auth: 'auth-key',
            eventId: $this->eventId,
        );

        $row = DB::table('push_subscriptions')->where('id', $id)->first();

        $this->assertSame('WEB', $row->platform);
        // jsonb does not preserve key order, so compare by content.
        $this->assertEqualsCanonicalizing(
            ['p256dh' => 'p256dh-key', 'auth' => 'auth-key'],
            json_decode((string) $row->keys, true)
        );
        $this->assertNull($row->revoked_at);
    }

    public function test_a_native_subscription_stores_its_token(): void
    {
        $id = $this->service->registerNative(
            subscriberType: 'DEVICE',
            subscriberId: 42,
            platform: 'FCM',
            token: 'fcm-token-abc',
        );

        $row = DB::table('push_subscriptions')->where('id', $id)->first();

        $this->assertSame('FCM', $row->platform);
        $this->assertSame('fcm-token-abc', $row->token);
        $this->assertNull($row->endpoint);
    }

    public function test_re_registering_the_same_endpoint_does_not_create_a_second_subscription(): void
    {
        $first = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/same', 'k1', 'a1');
        $second = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/same', 'k2', 'a2');

        $this->assertSame(
            $first,
            $second,
            'The same browser re-subscribing after a permission reset sends the same endpoint, '
            .'and two live rows would push twice to one phone.'
        );
        $this->assertSame(1, DB::table('push_subscriptions')->count());
    }

    public function test_re_registering_refreshes_the_keys(): void
    {
        $id = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/same', 'old', 'old');
        $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/same', 'new', 'new');

        $keys = json_decode((string) DB::table('push_subscriptions')->where('id', $id)->value('keys'), true);

        $this->assertSame('new', $keys['p256dh']);
    }

    public function test_re_registering_revives_a_revoked_subscription(): void
    {
        $id = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/revived', 'k', 'a');
        $this->service->revoke($id, 'UNSUBSCRIBED');

        $revived = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/revived', 'k', 'a');

        $row = DB::table('push_subscriptions')->where('id', $revived)->first();

        $this->assertSame($id, $revived);
        $this->assertNull(
            $row->revoked_at,
            'Somebody turning push back on should not need a second row.'
        );
        $this->assertNull($row->revoked_reason);
    }

    public function test_a_shared_device_rebinds_to_whoever_registered_it_last(): void
    {
        $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/kiosk', 'k', 'a');

        $secondUser = User::factory()->withAccount()->create();
        $id = $this->service->registerWeb(
            'USER',
            (int) $secondUser->id,
            'https://push.example.test/kiosk',
            'k',
            'a'
        );

        $this->assertSame(
            (int) $secondUser->id,
            (int) DB::table('push_subscriptions')->where('id', $id)->value('subscriber_id'),
            'An endpoint belongs to a browser profile, so a shared device handed over has to '
            .'stop sending the previous holder their alerts.'
        );
        $this->assertCount(0, $this->service->liveSubscriptionsFor('USER', $this->userId));
    }

    public function test_an_unknown_subscriber_type_is_refused(): void
    {
        $this->expectExceptionMessageMatches('/not a valid push subscriber type/');
        $this->service->registerWeb('ROBOT', 1, 'https://push.example.test/x', 'k', 'a');
    }

    public function test_an_unsupported_platform_is_refused(): void
    {
        $this->expectExceptionMessageMatches('/not a supported push platform/');
        $this->service->registerNative('USER', $this->userId, 'BLACKBERRY', 'token');
    }

    public function test_a_subscription_with_no_address_cannot_be_stored(): void
    {
        $this->expectException(QueryException::class);

        DB::table('push_subscriptions')->insert([
            'short_id' => 'ps_'.Str::lower(Str::random(20)),
            'subscriber_type' => 'USER',
            'subscriber_id' => $this->userId,
            'platform' => 'WEB',
            'endpoint' => null,
            'token' => null,
            'locale' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------- health

    public function test_a_gone_endpoint_is_revoked_at_once(): void
    {
        $id = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/dead', 'k', 'a');

        $this->service->recordFailure($id, httpStatus: 410);

        $row = DB::table('push_subscriptions')->where('id', $id)->first();

        $this->assertNotNull(
            $row->revoked_at,
            'Dead endpoints otherwise accumulate and slow every fan-out.'
        );
        $this->assertSame('ENDPOINT_GONE', $row->revoked_reason);
    }

    public function test_a_transient_failure_does_not_revoke_immediately(): void
    {
        $id = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/flaky', 'k', 'a');

        $this->service->recordFailure($id, httpStatus: 503);

        $row = DB::table('push_subscriptions')->where('id', $id)->first();

        $this->assertNull($row->revoked_at, 'One timeout is a network blip.');
        $this->assertSame(1, (int) $row->failure_count);
    }

    public function test_repeated_failures_eventually_revoke(): void
    {
        $id = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/gone', 'k', 'a');

        foreach (range(1, 5) as $ignored) {
            $this->service->recordFailure($id, httpStatus: 500);
        }

        $this->assertSame(
            'REPEATED_FAILURES',
            DB::table('push_subscriptions')->where('id', $id)->value('revoked_reason'),
            'Five failures in a row is a device that is not coming back.'
        );
    }

    public function test_a_success_clears_earlier_failures(): void
    {
        $id = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/recovers', 'k', 'a');

        $this->service->recordFailure($id, httpStatus: 500);
        $this->service->recordFailure($id, httpStatus: 500);
        $this->service->recordSuccess($id);

        $row = DB::table('push_subscriptions')->where('id', $id)->first();

        $this->assertSame(
            0,
            (int) $row->failure_count,
            'Carrying old failures forward would eventually revoke a live device.'
        );
        $this->assertNotNull($row->last_success_at);
    }

    public function test_a_revoked_subscription_is_kept_as_evidence(): void
    {
        $id = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/history', 'k', 'a');

        $this->service->revoke($id, 'UNSUBSCRIBED');

        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $id,
            'revoked_reason' => 'UNSUBSCRIBED',
        ]);
    }

    // ---------------------------------------------------------------- targeting

    public function test_only_live_subscriptions_are_returned(): void
    {
        $live = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/live', 'k', 'a');
        $dead = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/dead', 'k', 'a');

        $this->service->revoke($dead);

        $subscriptions = $this->service->liveSubscriptionsFor('USER', $this->userId);

        $this->assertCount(1, $subscriptions);
        $this->assertSame($live, (int) $subscriptions[0]->id);
    }

    public function test_one_subscriber_can_hold_several_devices(): void
    {
        $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/phone', 'k', 'a');
        $this->service->registerNative('USER', $this->userId, 'FCM', 'tablet-token');

        $this->assertCount(2, $this->service->liveSubscriptionsFor('USER', $this->userId));
    }

    public function test_another_subscribers_devices_are_not_returned(): void
    {
        $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/mine', 'k', 'a');
        $this->service->registerWeb('ATTENDEE', 99, 'https://push.example.test/theirs', 'k', 'a');

        $this->assertCount(1, $this->service->liveSubscriptionsFor('USER', $this->userId));
    }

    public function test_a_declined_category_is_skipped(): void
    {
        $id = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/picky', 'k', 'a');

        $this->service->setCategoryOptOuts($id, ['REMINDER']);

        $this->assertCount(0, $this->service->liveSubscriptionsFor('USER', $this->userId, 'REMINDER'));
        $this->assertCount(1, $this->service->liveSubscriptionsFor('USER', $this->userId, 'CRITICAL_OPERATIONAL'));
    }

    public function test_a_category_nobody_has_seen_yet_reaches_everybody(): void
    {
        $id = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/default', 'k', 'a');

        $this->service->setCategoryOptOuts($id, ['REMINDER']);

        $this->assertCount(
            1,
            $this->service->liveSubscriptionsFor('USER', $this->userId, 'A_CATEGORY_ADDED_LATER'),
            'Opt-outs are stored as what was declined, so a new category reaches everybody by '
            .'default rather than nobody.'
        );
    }

    public function test_turning_push_off_revokes_every_device(): void
    {
        $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/one', 'k', 'a');
        $this->service->registerNative('USER', $this->userId, 'APNS', 'token-two');

        $revoked = $this->service->revokeAllFor('USER', $this->userId);

        $this->assertSame(2, $revoked);
        $this->assertCount(0, $this->service->liveSubscriptionsFor('USER', $this->userId));
    }

    // ---------------------------------------------------------------- reporting

    public function test_health_separates_audiences_and_platforms(): void
    {
        $this->service->registerWeb('ATTENDEE', 1, 'https://push.example.test/a1', 'k', 'a', $this->eventId);
        $this->service->registerWeb('ATTENDEE', 2, 'https://push.example.test/a2', 'k', 'a', $this->eventId);
        $staffId = $this->service->registerNative('USER', $this->userId, 'FCM', 'staff-token', $this->eventId);

        $this->service->revoke($staffId);

        $health = $this->service->healthFor($this->eventId);

        $this->assertSame(2, $health['live']);
        $this->assertSame(1, $health['revoked']);
        $this->assertSame(2, $health['by_audience']['ATTENDEE']['WEB']['live']);
        $this->assertSame(
            0,
            $health['by_audience']['USER']['FCM']['live'],
            'Staff reachability is the figure that matters: a missed incident alert is an '
            .'operational failure, not a missed convenience.'
        );
    }

    public function test_another_events_subscriptions_are_not_counted(): void
    {
        $this->service->registerWeb('ATTENDEE', 1, 'https://push.example.test/other', 'k', 'a', $this->makeEvent());

        $this->assertSame(0, $this->service->healthFor($this->eventId)['live']);
    }

    public function test_an_unknown_subscription_cannot_be_revoked_into_existence(): void
    {
        $this->service->revoke(99999999);

        $this->assertSame(0, DB::table('push_subscriptions')->count());
    }

    public function test_revoking_twice_keeps_the_first_reason(): void
    {
        $id = $this->service->registerWeb('USER', $this->userId, 'https://push.example.test/once', 'k', 'a');

        $this->service->revoke($id, 'ENDPOINT_GONE');
        $this->service->revoke($id, 'UNSUBSCRIBED');

        $this->assertSame(
            'ENDPOINT_GONE',
            DB::table('push_subscriptions')->where('id', $id)->value('revoked_reason'),
            'The first reason is why it stopped working; a later one would overwrite the '
            .'evidence.'
        );
    }

    public function test_registering_a_subscriber_type_is_validated_for_native_too(): void
    {
        $this->expectException(ResourceConflictException::class);
        $this->service->registerNative('ROBOT', 1, 'FCM', 'token');
    }

    // ---------------------------------------------------------------- fixtures

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Push Organizer',
            'email' => 'push-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Push Event',
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
