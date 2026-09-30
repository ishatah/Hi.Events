<?php

namespace Tests\Feature\Services\Domain\Notification;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\NotificationCategory;
use HiEvents\DomainObjects\Enums\NotificationChannel;
use HiEvents\DomainObjects\Status\DeliveryStatus;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Notification\DTO\NotificationRecipientDTO;
use HiEvents\Services\Domain\Notification\NotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private NotificationService $service;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private CarbonImmutable $daytime;

    private CarbonImmutable $nighttime;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(NotificationService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent('Asia/Qatar');

        $this->daytime = CarbonImmutable::parse('2026-10-05 11:00:00', 'UTC');
        $this->nighttime = CarbonImmutable::parse('2026-10-05 20:30:00', 'UTC');
    }

    // ---------------------------------------------------------------- queueing

    public function test_queueing_freezes_one_delivery_per_recipient(): void
    {
        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::OPERATIONAL,
            templateKey: 'ticket.issued',
            recipients: $this->recipients(3),
            eventId: $this->eventId,
            now: $this->daytime,
        );

        $this->assertSame(3, $result->queued);
        $this->assertSame(
            3,
            DB::table('notification_deliveries')->where('notification_id', $result->notificationId)->count()
        );
    }

    public function test_a_notification_with_no_recipients_is_refused(): void
    {
        $this->expectExceptionMessageMatches('/at least one recipient/');

        $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::OPERATIONAL,
            templateKey: 'ticket.issued',
            recipients: new Collection,
        );
    }

    public function test_the_address_is_stored_hashed_and_masked_never_in_the_clear(): void
    {
        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::OPERATIONAL,
            templateKey: 'ticket.issued',
            recipients: new Collection([
                new NotificationRecipientDTO('ATTENDEE', 1, email: 'layla@example.test'),
            ]),
            eventId: $this->eventId,
            now: $this->daytime,
        );

        $delivery = DB::table('notification_deliveries')
            ->where('notification_id', $result->notificationId)
            ->first();

        $this->assertSame(hash('sha256', 'layla@example.test'), $delivery->address_hash);
        $this->assertSame('l***@example.test', $delivery->address_masked);
        $this->assertStringNotContainsString(
            'layla@example.test',
            json_encode($delivery),
            'The delivery log should not become a contact database.'
        );
    }

    public function test_an_unreachable_recipient_is_reported_not_swallowed(): void
    {
        // A critical message travels by push, phone, then email, none of which an attendee
        // with no contact details has. In-app is deliberately not in that order: somebody
        // who is not holding the app open has not been told.
        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::CRITICAL_OPERATIONAL,
            templateKey: 'gate.moved',
            recipients: new Collection([
                new NotificationRecipientDTO('ATTENDEE', 1, email: null),
            ]),
            eventId: $this->eventId,
            now: $this->daytime,
        );

        $this->assertSame(1, $result->skipped);
        $this->assertTrue(
            $result->hasUnreachableRecipients(),
            'A gate change that reached nobody is an operational fact somebody needs before '
            .'the gate opens.'
        );
    }

    public function test_an_invalid_address_is_skipped_rather_than_queued(): void
    {
        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::ACCOUNT,
            templateKey: 'password.reset',
            recipients: new Collection([
                new NotificationRecipientDTO('ATTENDEE', 1, email: 'not-an-email'),
            ]),
            eventId: $this->eventId,
            now: $this->daytime,
        );

        $this->assertSame(1, $result->skipped);
        $this->assertSame(0, $result->queued);
    }

    public function test_requeueing_the_same_recipient_does_not_duplicate_the_delivery(): void
    {
        $recipients = $this->recipients(1);

        $first = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::OPERATIONAL,
            templateKey: 'ticket.issued',
            recipients: $recipients,
            eventId: $this->eventId,
            now: $this->daytime,
        );

        DB::table('notification_deliveries')
            ->where('notification_id', $first->notificationId)
            ->update(['status' => DeliveryStatus::SENT->value]);

        // A second queue for the same notification id is what a retried fan-out job does.
        $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::OPERATIONAL,
            templateKey: 'ticket.issued',
            recipients: $recipients,
            eventId: $this->eventId,
            now: $this->daytime,
        );

        $this->assertSame(
            1,
            DB::table('notification_deliveries')->where('notification_id', $first->notificationId)->count()
        );
    }

    // ---------------------------------------------------------------- suppression

    public function test_a_suppressed_address_is_recorded_as_suppressed_not_silently_dropped(): void
    {
        $this->service->suppress(
            NotificationChannel::EMAIL,
            'optedout@example.test',
            'UNSUBSCRIBE',
            $this->accountId
        );

        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::OPERATIONAL,
            templateKey: 'ticket.issued',
            recipients: new Collection([
                new NotificationRecipientDTO('ATTENDEE', 1, email: 'optedout@example.test'),
            ]),
            eventId: $this->eventId,
            now: $this->daytime,
        );

        $this->assertSame(1, $result->suppressed);
        $this->assertSame(
            DeliveryStatus::SUPPRESSED->value,
            DB::table('notification_deliveries')->where('notification_id', $result->notificationId)->value('status'),
            'A row saying why nothing was sent is what answers the support ticket.'
        );
    }

    public function test_a_global_suppression_outranks_an_account(): void
    {
        $this->service->suppress(
            NotificationChannel::EMAIL,
            'gone@example.test',
            'COMPLAINT',
            accountId: null,
            scope: 'GLOBAL'
        );

        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::OPERATIONAL,
            templateKey: 'ticket.issued',
            recipients: new Collection([
                new NotificationRecipientDTO('ATTENDEE', 1, email: 'gone@example.test'),
            ]),
            eventId: $this->eventId,
            now: $this->daytime,
        );

        $this->assertSame(
            1,
            $result->suppressed,
            'Somebody who opted out at platform level must not be reachable through any '
            .'account on it.'
        );
    }

    public function test_another_accounts_suppression_does_not_apply(): void
    {
        $otherUser = User::factory()->withAccount()->create();

        $this->service->suppress(
            NotificationChannel::EMAIL,
            'shared@example.test',
            'UNSUBSCRIBE',
            (int) $otherUser->accounts()->first()->id
        );

        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::OPERATIONAL,
            templateKey: 'ticket.issued',
            recipients: new Collection([
                new NotificationRecipientDTO('ATTENDEE', 1, email: 'shared@example.test'),
            ]),
            eventId: $this->eventId,
            now: $this->daytime,
        );

        $this->assertSame(1, $result->queued);
    }

    public function test_suppressing_twice_is_the_same_as_once(): void
    {
        foreach ([1, 2] as $ignored) {
            $this->service->suppress(
                NotificationChannel::SMS,
                '+97433123456',
                'STOP_REPLY',
                $this->accountId
            );
        }

        $this->assertSame(
            1,
            DB::table('channel_suppressions')->where('account_id', $this->accountId)->count()
        );
    }

    public function test_a_phone_suppression_matches_however_the_number_was_written(): void
    {
        $this->service->suppress(NotificationChannel::SMS, '+974 3312 3456', 'STOP_REPLY', $this->accountId);

        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::STAFF_ALERT,
            templateKey: 'incident.assigned',
            recipients: new Collection([
                new NotificationRecipientDTO('USER', $this->userId, phone: '33123456'),
            ]),
            eventId: $this->eventId,
            now: $this->daytime,
        );

        $this->assertSame(
            1,
            $result->suppressed,
            'A STOP reply must stop messages to that number whatever shape it arrives in.'
        );
    }

    // ---------------------------------------------------------------- quiet hours

    public function test_a_reminder_at_night_is_deferred(): void
    {
        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::REMINDER,
            templateKey: 'session.starting',
            recipients: new Collection([
                new NotificationRecipientDTO('ATTENDEE', 1, pushToken: 'token-abc'),
            ]),
            eventId: $this->eventId,
            now: $this->nighttime,
        );

        $this->assertSame(1, $result->deferred);

        $delivery = DB::table('notification_deliveries')
            ->where('notification_id', $result->notificationId)
            ->first();

        $this->assertNotNull($delivery->deferred_until);
    }

    public function test_a_critical_operational_message_ignores_quiet_hours(): void
    {
        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::CRITICAL_OPERATIONAL,
            templateKey: 'gate.moved',
            recipients: new Collection([
                new NotificationRecipientDTO('ATTENDEE', 1, pushToken: 'token-abc'),
            ]),
            eventId: $this->eventId,
            now: $this->nighttime,
        );

        $this->assertSame(
            1,
            $result->queued,
            'A gate change at 23:00 is exactly the message somebody needs overnight.'
        );
    }

    public function test_quiet_hours_are_read_in_the_venue_timezone(): void
    {
        $utcEvent = $this->makeEvent('UTC');

        $queuedInQatar = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::REMINDER,
            templateKey: 'session.starting',
            recipients: new Collection([new NotificationRecipientDTO('ATTENDEE', 1, pushToken: 'a')]),
            eventId: $this->eventId,
            now: $this->nighttime,
        );

        $queuedInUtc = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::REMINDER,
            templateKey: 'session.starting',
            recipients: new Collection([new NotificationRecipientDTO('ATTENDEE', 2, pushToken: 'b')]),
            eventId: $utcEvent,
            now: $this->nighttime,
        );

        $this->assertSame(
            1,
            $queuedInQatar->deferred,
            '20:30 UTC is 23:30 in Doha, which is inside quiet hours there.'
        );
        $this->assertSame(
            1,
            $queuedInUtc->queued,
            'The same instant is 20:30 in UTC, which is not.'
        );
    }

    public function test_releasing_deferred_deliveries_makes_them_sendable(): void
    {
        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::REMINDER,
            templateKey: 'session.starting',
            recipients: new Collection([new NotificationRecipientDTO('ATTENDEE', 1, pushToken: 'a')]),
            eventId: $this->eventId,
            now: $this->nighttime,
        );

        $released = $this->service->releaseDeferred($this->nighttime->addHours(12));

        $this->assertSame(1, $released['released']);
        $this->assertSame(
            DeliveryStatus::PENDING->value,
            DB::table('notification_deliveries')->where('notification_id', $result->notificationId)->value('status')
        );
    }

    public function test_a_deferred_reminder_that_expired_while_waiting_is_dropped(): void
    {
        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::REMINDER,
            templateKey: 'session.starting',
            recipients: new Collection([new NotificationRecipientDTO('ATTENDEE', 1, pushToken: 'a')]),
            eventId: $this->eventId,
            expiresAt: $this->nighttime->addHours(2),
            now: $this->nighttime,
        );

        $released = $this->service->releaseDeferred($this->nighttime->addHours(12));

        $this->assertSame(1, $released['expired']);
        $this->assertSame(
            DeliveryStatus::EXPIRED->value,
            DB::table('notification_deliveries')->where('notification_id', $result->notificationId)->value('status'),
            'A reminder for a session that has already started is not worth sending late.'
        );
    }

    public function test_a_deferred_delivery_is_not_released_before_its_window_ends(): void
    {
        $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::REMINDER,
            templateKey: 'session.starting',
            recipients: new Collection([new NotificationRecipientDTO('ATTENDEE', 1, pushToken: 'a')]),
            eventId: $this->eventId,
            now: $this->nighttime,
        );

        $this->assertSame(0, $this->service->releaseDeferred($this->nighttime->addHour())['released']);
    }

    // ---------------------------------------------------------------- preferences

    public function test_switching_off_email_still_leaves_the_in_app_copy(): void
    {
        $this->setPreference(7, NotificationChannel::EMAIL, false);

        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::OPERATIONAL,
            templateKey: 'ticket.issued',
            recipients: new Collection([
                new NotificationRecipientDTO('ATTENDEE', 7, email: 'quiet@example.test'),
            ]),
            eventId: $this->eventId,
            now: $this->daytime,
        );

        $this->assertSame(1, $result->queued);
        $this->assertSame(
            NotificationChannel::IN_APP->value,
            DB::table('notification_deliveries')
                ->where('notification_id', $result->notificationId)
                ->value('channel'),
            'Switching off email means stop emailing me, not stop telling me.'
        );
    }

    public function test_switching_off_every_channel_leaves_the_recipient_unreachable(): void
    {
        $this->setPreference(8, NotificationChannel::EMAIL, false);
        $this->setPreference(8, NotificationChannel::IN_APP, false);

        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::OPERATIONAL,
            templateKey: 'ticket.issued',
            recipients: new Collection([
                new NotificationRecipientDTO('ATTENDEE', 8, email: 'silent@example.test'),
            ]),
            eventId: $this->eventId,
            now: $this->daytime,
        );

        $this->assertSame(0, $result->queued);
        $this->assertSame(1, $result->skipped);
    }

    public function test_an_account_message_cannot_be_switched_off(): void
    {
        DB::table('notification_preferences')->insert([
            'subject_type' => 'PERSON',
            'subject_id' => 7,
            'event_id' => null,
            'category' => NotificationCategory::ACCOUNT->value,
            'channel' => NotificationChannel::EMAIL->value,
            'enabled' => false,
            'source' => 'SELF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::ACCOUNT,
            templateKey: 'password.reset',
            recipients: new Collection([
                new NotificationRecipientDTO('ATTENDEE', 7, email: 'someone@example.test'),
            ]),
            now: $this->daytime,
        );

        $this->assertSame(
            1,
            $result->queued,
            'A password reset nobody receives is an account somebody has lost.'
        );
    }

    public function test_a_critical_operational_message_cannot_be_switched_off_either(): void
    {
        DB::table('notification_preferences')->insert([
            'subject_type' => 'PERSON',
            'subject_id' => 7,
            'event_id' => null,
            'category' => NotificationCategory::CRITICAL_OPERATIONAL->value,
            'channel' => NotificationChannel::PUSH->value,
            'enabled' => false,
            'source' => 'SELF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::CRITICAL_OPERATIONAL,
            templateKey: 'event.cancelled',
            recipients: new Collection([
                new NotificationRecipientDTO('ATTENDEE', 7, pushToken: 'token-abc'),
            ]),
            eventId: $this->eventId,
            now: $this->daytime,
        );

        $this->assertSame(1, $result->queued);
    }

    // ---------------------------------------------------------------- sending

    public function test_a_delivery_is_claimed_once(): void
    {
        $deliveryId = $this->queueOne();

        $this->assertTrue($this->service->claimForSending($deliveryId));
        $this->assertFalse(
            $this->service->claimForSending($deliveryId),
            'Two workers claiming the same delivery would send it twice.'
        );
    }

    public function test_a_metered_delivery_reclaimed_without_a_provider_id_is_unknown_not_resent(): void
    {
        $deliveryId = $this->queueOne(
            category: NotificationCategory::STAFF_ALERT,
            recipient: new NotificationRecipientDTO('USER', $this->userId, phone: '+97433123456'),
        );

        $this->service->claimForSending($deliveryId);

        $this->assertFalse($this->service->claimForSending($deliveryId));
        $this->assertSame(
            DeliveryStatus::UNKNOWN->value,
            DB::table('notification_deliveries')->where('id', $deliveryId)->value('status'),
            'A paid, interrupting message is never sent twice because a worker died at the '
            .'wrong moment.'
        );
    }

    public function test_marking_sent_records_the_provider_reference(): void
    {
        $deliveryId = $this->queueOne();

        $this->service->claimForSending($deliveryId);
        $this->service->markSent($deliveryId, 'ses', 'msg-123');

        $row = DB::table('notification_deliveries')->where('id', $deliveryId)->first();

        $this->assertSame(DeliveryStatus::SENT->value, $row->status);
        $this->assertSame('msg-123', $row->provider_message_id);
        $this->assertNotNull($row->sent_at);
    }

    public function test_a_receipt_advances_the_status(): void
    {
        $deliveryId = $this->queueOne();
        $this->service->claimForSending($deliveryId);
        $this->service->markSent($deliveryId, 'ses', 'msg-123');

        $this->assertTrue($this->service->applyReceipt('ses', 'msg-123', DeliveryStatus::DELIVERED));
        $this->assertSame(
            DeliveryStatus::DELIVERED->value,
            DB::table('notification_deliveries')->where('id', $deliveryId)->value('status')
        );
    }

    public function test_a_late_receipt_does_not_walk_the_status_backwards(): void
    {
        $deliveryId = $this->queueOne();
        $this->service->claimForSending($deliveryId);
        $this->service->markSent($deliveryId, 'ses', 'msg-123');
        $this->service->applyReceipt('ses', 'msg-123', DeliveryStatus::READ);

        $this->assertFalse(
            $this->service->applyReceipt('ses', 'msg-123', DeliveryStatus::DELIVERED),
            'Receipts arrive out of order; a DELIVERED landing after a READ must not lose '
            .'the stronger evidence.'
        );
        $this->assertSame(
            DeliveryStatus::READ->value,
            DB::table('notification_deliveries')->where('id', $deliveryId)->value('status')
        );
    }

    public function test_a_receipt_for_an_unknown_message_is_ignored(): void
    {
        $this->assertFalse(
            $this->service->applyReceipt('ses', 'never-issued', DeliveryStatus::DELIVERED),
            'A webhook carries only the provider id, so one we never issued is not evidence '
            .'of anything.'
        );
    }

    // ---------------------------------------------------------------- fallback

    public function test_a_failed_critical_delivery_escalates_to_the_next_channel(): void
    {
        $recipient = new NotificationRecipientDTO(
            'ATTENDEE',
            9,
            email: 'layla@example.test',
            phone: '+97433123456',
            pushToken: 'token-abc',
        );

        $deliveryId = $this->queueOne(
            category: NotificationCategory::CRITICAL_OPERATIONAL,
            recipient: $recipient,
        );

        $this->service->markFailed($deliveryId, 'NO_ACK');

        $escalatedId = $this->service->escalate($deliveryId, $recipient);

        $this->assertNotNull($escalatedId);

        $escalated = DB::table('notification_deliveries')->where('id', $escalatedId)->first();

        $this->assertSame(NotificationChannel::WHATSAPP->value, $escalated->channel);
        $this->assertSame($deliveryId, (int) $escalated->fallback_of_id);
    }

    public function test_escalation_never_repeats_a_channel(): void
    {
        $recipient = new NotificationRecipientDTO(
            'ATTENDEE',
            9,
            email: 'layla@example.test',
            pushToken: 'token-abc',
        );

        $deliveryId = $this->queueOne(
            category: NotificationCategory::CRITICAL_OPERATIONAL,
            recipient: $recipient,
        );

        $this->service->markFailed($deliveryId, 'NO_ACK');
        $emailId = $this->service->escalate($deliveryId, $recipient);

        $this->service->markFailed((int) $emailId, 'BOUNCED', bounced: true);

        $this->assertNull(
            $this->service->escalate((int) $emailId, $recipient),
            'With push and email both tried and no phone number, there is nowhere left to go.'
        );
    }

    public function test_escalation_never_reuses_the_same_address(): void
    {
        $recipient = new NotificationRecipientDTO(
            'ATTENDEE',
            9,
            email: 'same@example.test',
            phone: '+97433123456',
        );

        $deliveryId = $this->queueOne(
            category: NotificationCategory::CRITICAL_OPERATIONAL,
            recipient: $recipient,
        );

        $this->assertSame(
            NotificationChannel::WHATSAPP->value,
            DB::table('notification_deliveries')->where('id', $deliveryId)->value('channel')
        );

        $this->service->markFailed($deliveryId, 'UNREACHABLE');

        $escalatedId = $this->service->escalate($deliveryId, $recipient);

        // WhatsApp and SMS share the phone number, so SMS is skipped rather than tried:
        // escalating onto the address that just failed would only repeat the failure, and
        // for a metered channel it would pay to do so.
        $this->assertSame(
            NotificationChannel::EMAIL->value,
            DB::table('notification_deliveries')->where('id', $escalatedId)->value('channel'),
            'The next hop is the email address, not the other channel on the same number.'
        );

        $this->service->markFailed((int) $escalatedId, 'BOUNCED', bounced: true);

        $this->assertNull(
            $this->service->escalate((int) $escalatedId, $recipient),
            'With the phone and the email both failed, there is nowhere left to go.'
        );
    }

    public function test_an_account_message_never_changes_channel(): void
    {
        $recipient = new NotificationRecipientDTO(
            'ATTENDEE',
            9,
            email: 'layla@example.test',
            phone: '+97433123456',
        );

        $deliveryId = $this->queueOne(
            category: NotificationCategory::ACCOUNT,
            recipient: $recipient,
        );

        $this->service->markFailed($deliveryId, 'BOUNCED', bounced: true);

        $this->assertNull(
            $this->service->escalate($deliveryId, $recipient),
            'An auth code arriving by a second route is a security problem, not a convenience.'
        );
    }

    public function test_a_delivery_that_succeeded_does_not_escalate(): void
    {
        $recipient = new NotificationRecipientDTO('ATTENDEE', 9, email: 'layla@example.test', pushToken: 't');

        $deliveryId = $this->queueOne(
            category: NotificationCategory::CRITICAL_OPERATIONAL,
            recipient: $recipient,
        );

        $this->service->claimForSending($deliveryId);
        $this->service->markSent($deliveryId, 'fcm', 'm-1');

        $this->assertNull($this->service->escalate($deliveryId, $recipient));
    }

    public function test_escalation_skips_a_suppressed_channel(): void
    {
        $recipient = new NotificationRecipientDTO(
            'ATTENDEE',
            9,
            email: 'layla@example.test',
            phone: '+97433123456',
            pushToken: 'token-abc',
        );

        $this->service->suppress(
            NotificationChannel::WHATSAPP,
            '+97433123456',
            'STOP_REPLY',
            $this->accountId
        );
        $this->service->suppress(
            NotificationChannel::SMS,
            '+97433123456',
            'STOP_REPLY',
            $this->accountId
        );

        $deliveryId = $this->queueOne(
            category: NotificationCategory::CRITICAL_OPERATIONAL,
            recipient: $recipient,
        );

        $this->service->markFailed($deliveryId, 'NO_ACK');

        $escalatedId = $this->service->escalate($deliveryId, $recipient);

        $this->assertSame(
            NotificationChannel::EMAIL->value,
            DB::table('notification_deliveries')->where('id', $escalatedId)->value('channel'),
            'An opt-out still holds when the platform is escalating.'
        );
    }

    // ---------------------------------------------------------------- reporting

    public function test_delivery_stats_separate_reached_from_attempted(): void
    {
        $result = $this->service->queue(
            accountId: $this->accountId,
            category: NotificationCategory::OPERATIONAL,
            templateKey: 'ticket.issued',
            recipients: $this->recipients(3),
            eventId: $this->eventId,
            now: $this->daytime,
        );

        $ids = DB::table('notification_deliveries')
            ->where('notification_id', $result->notificationId)
            ->pluck('id');

        $this->service->markSent((int) $ids[0], 'ses', 'm-1');
        $this->service->markFailed((int) $ids[1], 'BOUNCED', bounced: true);

        $stats = $this->service->deliveryStats($result->notificationId);

        $this->assertSame(3, $stats['total']);
        $this->assertSame(
            1,
            $stats['reached'],
            'A send that bounced and a send still pending are both not delivered.'
        );
    }

    public function test_an_unknown_outcome_does_not_count_as_reached(): void
    {
        $deliveryId = $this->queueOne(
            category: NotificationCategory::STAFF_ALERT,
            recipient: new NotificationRecipientDTO('USER', $this->userId, phone: '+97433123456'),
        );

        $this->service->claimForSending($deliveryId);
        $this->service->claimForSending($deliveryId);

        $notificationId = (int) DB::table('notification_deliveries')
            ->where('id', $deliveryId)
            ->value('notification_id');

        $this->assertSame(
            0,
            $this->service->deliveryStats($notificationId)['reached'],
            'UNKNOWN means a paid send may or may not have happened; counting it as success '
            .'would hide it.'
        );
    }

    // ---------------------------------------------------------------- fixtures

    private function queueOne(
        ?NotificationCategory $category = null,
        ?NotificationRecipientDTO $recipient = null,
    ): int {
        $result = $this->service->queue(
            accountId: $this->accountId,
            category: $category ?? NotificationCategory::OPERATIONAL,
            templateKey: 'ticket.issued',
            recipients: new Collection([
                $recipient ?? new NotificationRecipientDTO('ATTENDEE', 1, email: 'layla@example.test'),
            ]),
            eventId: $this->eventId,
            now: $this->daytime,
        );

        return (int) DB::table('notification_deliveries')
            ->where('notification_id', $result->notificationId)
            ->value('id');
    }

    private function setPreference(int $subjectId, NotificationChannel $channel, bool $enabled): void
    {
        DB::table('notification_preferences')->insert([
            'subject_type' => 'PERSON',
            'subject_id' => $subjectId,
            'event_id' => null,
            'category' => NotificationCategory::OPERATIONAL->value,
            'channel' => $channel->value,
            'enabled' => $enabled,
            'source' => 'SELF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, NotificationRecipientDTO>
     */
    private function recipients(int $count): Collection
    {
        return Collection::range(1, $count)->map(
            static fn (int $index): NotificationRecipientDTO => new NotificationRecipientDTO(
                recipientType: 'ATTENDEE',
                recipientId: $index,
                email: 'recipient'.$index.'@example.test',
            )
        );
    }

    private function makeEvent(string $timezone): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Notify Organizer',
            'email' => 'nt-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => $timezone,
            'currency' => 'QAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Notify Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(5),
            'timezone' => $timezone,
            'currency' => 'QAR',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
