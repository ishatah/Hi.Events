<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Notification;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\NotificationCategory;
use HiEvents\DomainObjects\Enums\NotificationChannel;
use HiEvents\DomainObjects\Status\DeliveryStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Notification\DTO\NotificationRecipientDTO;
use HiEvents\Services\Domain\Notification\DTO\QueuedNotificationDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Decides who is notified, when, and on which channel, and records what happened.
 *
 * This is the whole reason the bus exists. Routing a message to a channel is the easy part;
 * suppression, quiet hours, criticality and a delivery log that reconciles are what the
 * messaging plans actually need, and none of them fall out of a channel adapter.
 *
 * Recipients are frozen as delivery rows at queue time rather than re-resolved at send time,
 * because an audience re-resolved an hour later answers a different question.
 *
 * @see docs/arzo-master-plan/69-notifications-architecture.md
 */
class NotificationService
{
    private const DEFAULT_QUIET_START_HOUR = 22;

    private const DEFAULT_QUIET_END_HOUR = 8;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly PhoneNumberService $phoneNumberService,
    ) {}

    /**
     * Queues one logical notification and its per-recipient deliveries.
     *
     * @param  Collection<int, NotificationRecipientDTO>  $recipients
     *
     * @throws ResourceConflictException
     */
    public function queue(
        int $accountId,
        NotificationCategory $category,
        string $templateKey,
        Collection $recipients,
        ?int $eventId = null,
        ?array $context = null,
        ?array $audience = null,
        ?CarbonImmutable $expiresAt = null,
        string $triggeredByType = 'SYSTEM',
        ?int $triggeredById = null,
        ?string $timezone = null,
        ?CarbonImmutable $now = null,
    ): QueuedNotificationDTO {
        if ($recipients->isEmpty()) {
            throw new ResourceConflictException(__('A notification needs at least one recipient.'));
        }

        $at = $now ?? CarbonImmutable::now();
        $venueTimezone = $timezone ?? $this->eventTimezone($eventId);

        return $this->databaseManager->transaction(function () use (
            $accountId,
            $category,
            $templateKey,
            $recipients,
            $eventId,
            $context,
            $audience,
            $expiresAt,
            $triggeredByType,
            $triggeredById,
            $venueTimezone,
            $at
        ): QueuedNotificationDTO {
            $notificationId = (int) $this->databaseManager->table('notifications')->insertGetId([
                'short_id' => 'nt_'.Str::lower(Str::random(20)),
                'account_id' => $accountId,
                'event_id' => $eventId,
                'category' => $category->value,
                'template_key' => $templateKey,
                'context' => $context !== null ? json_encode($context) : null,
                'audience' => $audience !== null ? json_encode($audience) : null,
                'triggered_by_type' => $triggeredByType,
                'triggered_by_id' => $triggeredById,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counts = [
                'queued' => 0,
                'suppressed' => 0,
                'deferred' => 0,
                'skipped' => 0,
            ];

            foreach ($recipients as $recipient) {
                $channel = $this->firstUsableChannel($category, $recipient, $eventId);

                if ($channel === null) {
                    $counts['skipped']++;

                    continue;
                }

                $address = $recipient->addressFor($channel);
                $normalised = $this->normaliseAddress($channel, $address);

                if ($normalised === null) {
                    $counts['skipped']++;

                    continue;
                }

                $status = $this->decideStatus(
                    $accountId,
                    $category,
                    $channel,
                    $normalised['hash'],
                    $venueTimezone,
                    $at
                );

                $counts[$this->countKeyFor($status)]++;

                $this->insertDelivery(
                    notificationId: $notificationId,
                    recipient: $recipient,
                    channel: $channel,
                    addressHash: $normalised['hash'],
                    addressMasked: $normalised['masked'],
                    status: $status,
                    deferredUntil: $status === DeliveryStatus::DEFERRED
                        ? $this->quietHoursEnd($venueTimezone, $at)
                        : null,
                );
            }

            return new QueuedNotificationDTO(
                notificationId: $notificationId,
                queued: $counts['queued'],
                suppressed: $counts['suppressed'],
                deferred: $counts['deferred'],
                skipped: $counts['skipped'],
            );
        });
    }

    /**
     * Claims a delivery for sending.
     *
     * Writes SENDING before the provider is called, so a retry that finds SENDING without a
     * provider id knows the outcome is genuinely unknown. For a metered channel that means
     * UNKNOWN rather than a second send: a paid, interrupting message is never sent twice
     * just because a worker died at the wrong moment.
     */
    public function claimForSending(int $deliveryId): bool
    {
        return $this->databaseManager->transaction(function () use ($deliveryId): bool {
            $delivery = $this->databaseManager->table('notification_deliveries')
                ->where('id', $deliveryId)
                ->lockForUpdate()
                ->first();

            if ($delivery === null) {
                return false;
            }

            $status = DeliveryStatus::from((string) $delivery->status);
            $channel = NotificationChannel::from((string) $delivery->channel);

            if ($status === DeliveryStatus::SENDING) {
                if ($channel->isMetered() && $delivery->provider_message_id === null) {
                    $this->markUnknown($deliveryId);
                }

                return false;
            }

            if ($status !== DeliveryStatus::PENDING && $status !== DeliveryStatus::DEFERRED) {
                return false;
            }

            if ($this->hasExpired($delivery)) {
                $this->databaseManager->table('notification_deliveries')
                    ->where('id', $deliveryId)
                    ->update([
                        'status' => DeliveryStatus::EXPIRED->value,
                        'updated_at' => now(),
                    ]);

                return false;
            }

            $this->databaseManager->table('notification_deliveries')
                ->where('id', $deliveryId)
                ->update(['status' => DeliveryStatus::SENDING->value, 'updated_at' => now()]);

            return true;
        });
    }

    public function markSent(int $deliveryId, string $provider, ?string $providerMessageId = null): void
    {
        $this->databaseManager->table('notification_deliveries')
            ->where('id', $deliveryId)
            ->update([
                'status' => DeliveryStatus::SENT->value,
                'provider' => $provider,
                'provider_message_id' => $providerMessageId,
                'sent_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function markFailed(
        int $deliveryId,
        string $errorCode,
        ?string $errorDetail = null,
        bool $bounced = false,
    ): void {
        $this->databaseManager->table('notification_deliveries')
            ->where('id', $deliveryId)
            ->update([
                'status' => ($bounced ? DeliveryStatus::BOUNCED : DeliveryStatus::FAILED)->value,
                'error_code' => $errorCode,
                'error_detail' => $errorDetail !== null ? Str::limit($errorDetail, 480) : null,
                'failed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Applies a provider receipt.
     *
     * Matched on the provider's own id rather than ours, because that is all a webhook
     * carries. A receipt for an id we never issued is ignored rather than trusted.
     */
    public function applyReceipt(
        string $provider,
        string $providerMessageId,
        DeliveryStatus $status,
        ?string $errorCode = null,
    ): bool {
        $delivery = $this->databaseManager->table('notification_deliveries')
            ->where('provider', $provider)
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if ($delivery === null) {
            return false;
        }

        $current = DeliveryStatus::from((string) $delivery->status);

        // Receipts arrive out of order. A DELIVERED that lands after a READ must not walk the
        // status backwards and lose the stronger evidence.
        if ($this->statusRank($status) <= $this->statusRank($current)) {
            return false;
        }

        $changes = ['status' => $status->value, 'updated_at' => now()];

        if ($status === DeliveryStatus::DELIVERED) {
            $changes['delivered_at'] = now();
        }

        if ($status === DeliveryStatus::READ) {
            $changes['read_at'] = now();
        }

        if ($status === DeliveryStatus::FAILED || $status === DeliveryStatus::BOUNCED) {
            $changes['failed_at'] = now();
            $changes['error_code'] = $errorCode;
        }

        $this->databaseManager->table('notification_deliveries')
            ->where('id', $delivery->id)
            ->update($changes);

        return true;
    }

    /**
     * Escalates a failed delivery onto the next channel the category allows.
     *
     * Never repeats a channel already tried for this recipient, and never re-sends to the
     * same address: an escalation that lands on the address that just bounced is noise.
     *
     * @throws ResourceConflictException
     */
    public function escalate(int $deliveryId, NotificationRecipientDTO $recipient): ?int
    {
        return $this->databaseManager->transaction(function () use ($deliveryId, $recipient): ?int {
            $delivery = $this->databaseManager->table('notification_deliveries')
                ->where('id', $deliveryId)
                ->lockForUpdate()
                ->first();

            if ($delivery === null) {
                throw new ResourceConflictException(__('That delivery could not be found.'));
            }

            if (! DeliveryStatus::from((string) $delivery->status)->warrantsFallback()) {
                return null;
            }

            $notification = $this->databaseManager->table('notifications')
                ->where('id', $delivery->notification_id)
                ->first();

            $category = NotificationCategory::from((string) $notification->category);

            if (! $category->allowsFallback()) {
                return null;
            }

            $tried = $this->databaseManager->table('notification_deliveries')
                ->where('notification_id', $delivery->notification_id)
                ->where('recipient_type', $delivery->recipient_type)
                ->where('recipient_id', $delivery->recipient_id)
                ->pluck('channel')
                ->map(static fn ($channel): string => (string) $channel)
                ->all();

            $usedAddresses = $this->databaseManager->table('notification_deliveries')
                ->where('notification_id', $delivery->notification_id)
                ->where('recipient_type', $delivery->recipient_type)
                ->where('recipient_id', $delivery->recipient_id)
                ->pluck('address_hash')
                ->map(static fn ($hash): string => (string) $hash)
                ->all();

            foreach ($category->channelOrder() as $channel) {
                if (in_array($channel->value, $tried, true)) {
                    continue;
                }

                $normalised = $this->normaliseAddress($channel, $recipient->addressFor($channel));

                if ($normalised === null || in_array($normalised['hash'], $usedAddresses, true)) {
                    continue;
                }

                if ($this->isSuppressed((int) $notification->account_id, $channel, $normalised['hash'])) {
                    continue;
                }

                return $this->insertDelivery(
                    notificationId: (int) $delivery->notification_id,
                    recipient: $recipient,
                    channel: $channel,
                    addressHash: $normalised['hash'],
                    addressMasked: $normalised['masked'],
                    status: DeliveryStatus::PENDING,
                    fallbackOfId: (int) $delivery->id,
                );
            }

            return null;
        });
    }

    /**
     * Releases deferred deliveries whose quiet window has passed, dropping any that expired
     * while waiting — a reminder for a session that has started is not worth sending.
     *
     * @return array{released: int, expired: int}
     */
    public function releaseDeferred(?CarbonImmutable $now = null): array
    {
        $at = $now ?? CarbonImmutable::now();

        $expired = $this->databaseManager->table('notification_deliveries')
            ->join('notifications', 'notifications.id', '=', 'notification_deliveries.notification_id')
            ->where('notification_deliveries.status', DeliveryStatus::DEFERRED->value)
            ->whereNotNull('notifications.expires_at')
            ->where('notifications.expires_at', '<=', $at)
            ->pluck('notification_deliveries.id');

        if ($expired->isNotEmpty()) {
            $this->databaseManager->table('notification_deliveries')
                ->whereIn('id', $expired)
                ->update(['status' => DeliveryStatus::EXPIRED->value, 'updated_at' => now()]);
        }

        $released = $this->databaseManager->table('notification_deliveries')
            ->where('status', DeliveryStatus::DEFERRED->value)
            ->whereNotNull('deferred_until')
            ->where('deferred_until', '<=', $at)
            ->update(['status' => DeliveryStatus::PENDING->value, 'updated_at' => now()]);

        return ['released' => $released, 'expired' => $expired->count()];
    }

    public function suppress(
        NotificationChannel $channel,
        string $address,
        string $reason,
        ?int $accountId = null,
        string $scope = 'ACCOUNT',
        ?string $detail = null,
    ): void {
        $normalised = $this->normaliseAddress($channel, $address);

        if ($normalised === null) {
            return;
        }

        // Opting out twice is the same as opting out once, so a duplicate is not an error.
        // Checked rather than caught: on Postgres a constraint violation aborts the
        // surrounding transaction, and catching it leaves every later query in this request
        // failing with "transaction is aborted".
        $alreadySuppressed = $this->databaseManager->table('channel_suppressions')
            ->where('channel', $channel->value)
            ->where('address_hash', $normalised['hash'])
            ->where('scope', $scope)
            ->when(
                $accountId === null,
                static fn ($query) => $query->whereNull('account_id'),
                static fn ($query) => $query->where('account_id', $accountId)
            )
            ->exists();

        if ($alreadySuppressed) {
            return;
        }

        $this->databaseManager->table('channel_suppressions')->insert([
            'account_id' => $accountId,
            'channel' => $channel->value,
            'address_hash' => $normalised['hash'],
            'scope' => $scope,
            'reason' => $reason,
            'detail' => $detail,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function deliveryStats(int $notificationId): array
    {
        $rows = $this->databaseManager->table('notification_deliveries')
            ->where('notification_id', $notificationId)
            ->selectRaw('channel, status, count(*) as total')
            ->groupBy('channel', 'status')
            ->get();

        $byChannel = [];
        $reached = 0;
        $total = 0;

        foreach ($rows as $row) {
            $byChannel[(string) $row->channel][(string) $row->status] = (int) $row->total;
            $total += (int) $row->total;

            if (DeliveryStatus::from((string) $row->status)->reachedRecipient()) {
                $reached += (int) $row->total;
            }
        }

        return [
            'total' => $total,
            'reached' => $reached,
            'by_channel' => $byChannel,
        ];
    }

    private function countKeyFor(DeliveryStatus $status): string
    {
        return match ($status) {
            DeliveryStatus::SUPPRESSED => 'suppressed',
            DeliveryStatus::DEFERRED => 'deferred',
            default => 'queued',
        };
    }

    private function insertDelivery(
        int $notificationId,
        NotificationRecipientDTO $recipient,
        NotificationChannel $channel,
        string $addressHash,
        string $addressMasked,
        DeliveryStatus $status,
        ?CarbonImmutable $deferredUntil = null,
        ?int $fallbackOfId = null,
    ): int {
        // The unique key on (notification, recipient, channel) is what makes fan-out
        // idempotent; a retry finding its own earlier row is the intended outcome. Looked up
        // rather than caught, because on Postgres a constraint violation aborts the
        // surrounding transaction and every later query in it then fails too.
        $existingId = $this->databaseManager->table('notification_deliveries')
            ->where('notification_id', $notificationId)
            ->where('recipient_type', $recipient->recipientType)
            ->where('recipient_id', $recipient->recipientId)
            ->where('channel', $channel->value)
            ->value('id');

        if ($existingId !== null) {
            return (int) $existingId;
        }

        return (int) $this->databaseManager->table('notification_deliveries')->insertGetId([
            'notification_id' => $notificationId,
            'recipient_type' => $recipient->recipientType,
            'recipient_id' => $recipient->recipientId,
            'channel' => $channel->value,
            'address_hash' => $addressHash,
            'address_masked' => $addressMasked,
            'status' => $status->value,
            'fallback_of_id' => $fallbackOfId,
            'deferred_until' => $deferredUntil,
            'queued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function decideStatus(
        int $accountId,
        NotificationCategory $category,
        NotificationChannel $channel,
        string $addressHash,
        string $timezone,
        CarbonImmutable $at,
    ): DeliveryStatus {
        if ($this->isSuppressed($accountId, $channel, $addressHash)) {
            return DeliveryStatus::SUPPRESSED;
        }

        if (! $category->bypassesQuietHours() && $this->isQuietHours($timezone, $at)) {
            return DeliveryStatus::DEFERRED;
        }

        return DeliveryStatus::PENDING;
    }

    private function isSuppressed(int $accountId, NotificationChannel $channel, string $addressHash): bool
    {
        return $this->databaseManager->table('channel_suppressions')
            ->where('channel', $channel->value)
            ->where('address_hash', $addressHash)
            ->where(static function ($query) use ($accountId): void {
                // A global suppression outranks an account: somebody who opted out at the
                // platform level must not be reachable through any account on it.
                $query->whereNull('account_id')->orWhere('account_id', $accountId);
            })
            ->exists();
    }

    private function firstUsableChannel(
        NotificationCategory $category,
        NotificationRecipientDTO $recipient,
        ?int $eventId,
    ): ?NotificationChannel {
        foreach ($category->channelOrder() as $channel) {
            if ($recipient->addressFor($channel) === null) {
                continue;
            }

            if (! $this->isChannelEnabled($category, $channel, $recipient, $eventId)) {
                continue;
            }

            return $channel;
        }

        return null;
    }

    private function isChannelEnabled(
        NotificationCategory $category,
        NotificationChannel $channel,
        NotificationRecipientDTO $recipient,
        ?int $eventId,
    ): bool {
        if (! $category->canBeDisabled()) {
            return true;
        }

        $preference = $this->databaseManager->table('notification_preferences')
            ->where('subject_type', $recipient->subjectType())
            ->where('subject_id', $recipient->recipientId)
            ->where('category', $category->value)
            ->where('channel', $channel->value)
            ->where(static function ($query) use ($eventId): void {
                $query->whereNull('event_id');

                if ($eventId !== null) {
                    $query->orWhere('event_id', $eventId);
                }
            })
            // An event-specific choice is more deliberate than an account-wide default.
            ->orderByRaw('event_id is null')
            ->value('enabled');

        return $preference === null || (bool) $preference;
    }

    /**
     * @return array{hash: string, masked: string}|null
     */
    private function normaliseAddress(NotificationChannel $channel, ?string $address): ?array
    {
        if ($address === null || trim($address) === '') {
            return null;
        }

        if ($channel->usesPhoneNumber()) {
            if (! $this->phoneNumberService->isValid($address)) {
                return null;
            }

            $e164 = $this->phoneNumberService->toE164($address);

            return [
                'hash' => hash('sha256', $e164),
                'masked' => $this->phoneNumberService->mask($e164),
            ];
        }

        if ($channel === NotificationChannel::EMAIL) {
            $email = Str::lower(trim($address));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }

            return ['hash' => hash('sha256', $email), 'masked' => $this->maskEmail($email)];
        }

        $token = trim($address);

        return ['hash' => hash('sha256', $token), 'masked' => Str::limit($token, 8, '***')];
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    private function isQuietHours(string $timezone, CarbonImmutable $at): bool
    {
        $hour = (int) $at->setTimezone($timezone)->format('G');

        return $hour >= self::DEFAULT_QUIET_START_HOUR || $hour < self::DEFAULT_QUIET_END_HOUR;
    }

    private function quietHoursEnd(string $timezone, CarbonImmutable $at): CarbonImmutable
    {
        $local = $at->setTimezone($timezone);

        $end = $local->setTime(self::DEFAULT_QUIET_END_HOUR, 0);

        if ($local->hour >= self::DEFAULT_QUIET_START_HOUR) {
            $end = $end->addDay();
        }

        return $end->setTimezone('UTC');
    }

    private function hasExpired(object $delivery): bool
    {
        $expiresAt = $this->databaseManager->table('notifications')
            ->where('id', $delivery->notification_id)
            ->value('expires_at');

        return $expiresAt !== null && now()->gt($expiresAt);
    }

    private function markUnknown(int $deliveryId): void
    {
        $this->databaseManager->table('notification_deliveries')
            ->where('id', $deliveryId)
            ->update([
                'status' => DeliveryStatus::UNKNOWN->value,
                'error_code' => 'SEND_OUTCOME_UNKNOWN',
                'error_detail' => 'Claimed for sending with no provider id; not resent because '
                    .'the channel is metered.',
                'updated_at' => now(),
            ]);
    }

    private function statusRank(DeliveryStatus $status): int
    {
        return match ($status) {
            DeliveryStatus::PENDING, DeliveryStatus::DEFERRED => 0,
            DeliveryStatus::SENDING => 1,
            DeliveryStatus::SENT => 2,
            DeliveryStatus::DELIVERED => 3,
            DeliveryStatus::READ => 4,
            DeliveryStatus::UNKNOWN => 2,
            DeliveryStatus::FAILED, DeliveryStatus::BOUNCED,
            DeliveryStatus::SUPPRESSED, DeliveryStatus::EXPIRED => 5,
        };
    }

    private function eventTimezone(?int $eventId): string
    {
        if ($eventId === null) {
            return config('app.timezone', 'UTC');
        }

        return (string) ($this->databaseManager->table('events')
            ->where('id', $eventId)
            ->value('timezone') ?? 'UTC');
    }
}
