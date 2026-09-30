<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Notification;

use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The push subscription registry.
 *
 * Staff push is built before attendee push on purpose: the devices are known, the audience is
 * small and wants the messages, and a missed incident alert is an operational failure rather
 * than a missed convenience. Attendee push on iPhone reaches only people who installed the PWA
 * and then opted in, which at a one-day event is likely a minority — so it cannot be the only
 * channel for anything that matters, and the notification bus already handles that fallback.
 *
 * This service owns registration, targeting and revocation. It does not talk to a push
 * provider: sending belongs behind the channel adapters in the bus, and no provider has been
 * chosen.
 *
 * @see docs/arzo-master-plan/44-push-notifications.md
 */
class PushSubscriptionService
{
    private const MAX_FAILURES_BEFORE_REVOKE = 5;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * Registers a web push subscription.
     *
     * @throws ResourceConflictException
     */
    public function registerWeb(
        string $subscriberType,
        int $subscriberId,
        string $endpoint,
        string $p256dh,
        string $auth,
        ?int $eventId = null,
        ?string $userAgent = null,
        string $locale = 'en',
    ): int {
        $this->assertSubscriberType($subscriberType);

        return $this->upsert(
            column: 'endpoint',
            value: $endpoint,
            attributes: [
                'subscriber_type' => $subscriberType,
                'subscriber_id' => $subscriberId,
                'event_id' => $eventId,
                'platform' => 'WEB',
                'endpoint' => $endpoint,
                'token' => null,
                'keys' => json_encode(['p256dh' => $p256dh, 'auth' => $auth]),
                'user_agent' => $userAgent !== null ? Str::limit($userAgent, 480) : null,
                'locale' => $locale,
            ],
        );
    }

    /**
     * Registers a native subscription.
     *
     * @throws ResourceConflictException
     */
    public function registerNative(
        string $subscriberType,
        int $subscriberId,
        string $platform,
        string $token,
        ?int $eventId = null,
        string $locale = 'en',
    ): int {
        $this->assertSubscriberType($subscriberType);

        if (! in_array($platform, ['FCM', 'APNS'], true)) {
            throw new ResourceConflictException(__('That is not a supported push platform.'));
        }

        return $this->upsert(
            column: 'token',
            value: $token,
            attributes: [
                'subscriber_type' => $subscriberType,
                'subscriber_id' => $subscriberId,
                'event_id' => $eventId,
                'platform' => $platform,
                'endpoint' => null,
                'token' => $token,
                'keys' => null,
                'locale' => $locale,
            ],
        );
    }

    /**
     * Records that a send reached the provider.
     *
     * Also clears the failure count: a subscription that failed twice and then worked is
     * healthy, and carrying the old failures forward would eventually revoke a live device.
     */
    public function recordSuccess(int $subscriptionId): void
    {
        $this->databaseManager->table('push_subscriptions')
            ->where('id', $subscriptionId)
            ->update([
                'last_success_at' => now(),
                'failure_count' => 0,
                'updated_at' => now(),
            ]);
    }

    /**
     * Records a failed send.
     *
     * A 404 or 410 from the push service means the endpoint is gone for good, so it is revoked
     * at once rather than retried: dead endpoints otherwise accumulate and slow every fan-out.
     * Other failures are counted, because one timeout is a network blip and five in a row is a
     * device that is not coming back.
     */
    public function recordFailure(int $subscriptionId, ?int $httpStatus = null): void
    {
        if ($httpStatus === 404 || $httpStatus === 410) {
            $this->revoke($subscriptionId, 'ENDPOINT_GONE');

            return;
        }

        $this->databaseManager->table('push_subscriptions')
            ->where('id', $subscriptionId)
            ->increment('failure_count', 1, ['updated_at' => now()]);

        $failures = (int) $this->databaseManager->table('push_subscriptions')
            ->where('id', $subscriptionId)
            ->value('failure_count');

        if ($failures >= self::MAX_FAILURES_BEFORE_REVOKE) {
            $this->revoke($subscriptionId, 'REPEATED_FAILURES');
        }
    }

    public function revoke(int $subscriptionId, string $reason = 'UNSUBSCRIBED'): void
    {
        $this->databaseManager->table('push_subscriptions')
            ->where('id', $subscriptionId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'revoked_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    /**
     * Revokes every subscription a subscriber holds, which is what happens when somebody turns
     * push off rather than swapping devices.
     */
    public function revokeAllFor(string $subscriberType, int $subscriberId): int
    {
        return $this->databaseManager->table('push_subscriptions')
            ->where('subscriber_type', $subscriberType)
            ->where('subscriber_id', $subscriberId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'revoked_reason' => 'UNSUBSCRIBED',
                'updated_at' => now(),
            ]);
    }

    /**
     * Where to send, for one subscriber.
     *
     * @return Collection<int, object>
     */
    public function liveSubscriptionsFor(
        string $subscriberType,
        int $subscriberId,
        ?string $category = null,
    ): Collection {
        return $this->databaseManager->table('push_subscriptions')
            ->where('subscriber_type', $subscriberType)
            ->where('subscriber_id', $subscriberId)
            ->whereNull('revoked_at')
            ->get()
            ->reject(fn (object $subscription): bool => $category !== null
                && ! $this->acceptsCategory($subscription, $category))
            ->values();
    }

    /**
     * Sets per-category opt-outs for one subscription.
     *
     * Stored as the categories the subscriber declined rather than those they accepted, so a
     * category added later reaches everybody by default instead of nobody.
     *
     * @param  array<int, string>  $declinedCategories
     */
    public function setCategoryOptOuts(int $subscriptionId, array $declinedCategories): void
    {
        $this->databaseManager->table('push_subscriptions')
            ->where('id', $subscriptionId)
            ->update([
                'categories' => json_encode(['declined' => array_values(array_unique($declinedCategories))]),
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function healthFor(int $eventId): array
    {
        $rows = $this->databaseManager->table('push_subscriptions')
            ->where('event_id', $eventId)
            ->selectRaw('subscriber_type, platform, count(*) as total, count(revoked_at) as revoked')
            ->groupBy('subscriber_type', 'platform')
            ->get();

        $live = 0;
        $revoked = 0;
        $byAudience = [];

        foreach ($rows as $row) {
            $liveHere = (int) $row->total - (int) $row->revoked;

            $live += $liveHere;
            $revoked += (int) $row->revoked;

            $byAudience[(string) $row->subscriber_type][(string) $row->platform] = [
                'live' => $liveHere,
                'revoked' => (int) $row->revoked,
            ];
        }

        return [
            'live' => $live,
            'revoked' => $revoked,
            'by_audience' => $byAudience,
        ];
    }

    private function acceptsCategory(object $subscription, string $category): bool
    {
        if ($subscription->categories === null) {
            return true;
        }

        $declined = json_decode((string) $subscription->categories, true)['declined'] ?? [];

        return ! in_array($category, $declined, true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(string $column, string $value, array $attributes): int
    {
        return $this->databaseManager->transaction(function () use ($column, $value, $attributes): int {
            // The same browser re-subscribing after a permission reset sends the same
            // endpoint. Reviving that row rather than inserting a second keeps one live
            // subscription per device, so a fan-out cannot push twice to the same phone.
            //
            // Matched on the endpoint alone, not the subscriber: an endpoint belongs to a
            // browser profile, so when a shared device is registered by somebody else it has
            // to move to them. Leaving it bound to the previous holder would keep sending
            // their alerts to a device they have handed over.
            $existing = $this->databaseManager->table('push_subscriptions')
                ->where($column, $value)
                ->orderByDesc('id')
                ->first();

            if ($existing !== null) {
                $this->databaseManager->table('push_subscriptions')
                    ->where('id', $existing->id)
                    ->update($attributes + [
                        'revoked_at' => null,
                        'revoked_reason' => null,
                        'failure_count' => 0,
                        'updated_at' => now(),
                    ]);

                return (int) $existing->id;
            }

            return (int) $this->databaseManager->table('push_subscriptions')->insertGetId(
                $attributes + [
                    'short_id' => 'ps_'.Str::lower(Str::random(20)),
                    'failure_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });
    }

    /**
     * @throws ResourceConflictException
     */
    private function assertSubscriberType(string $subscriberType): void
    {
        if (! in_array($subscriberType, ['ATTENDEE', 'USER', 'DEVICE'], true)) {
            throw new ResourceConflictException(__('That is not a valid push subscriber type.'));
        }
    }
}
