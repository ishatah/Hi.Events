<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Infrastructure\Webhook;

use HiEvents\DomainObjects\Status\WebhookStatus;
use HiEvents\Jobs\Order\Webhook\DispatchOccurrenceWebhookJob;
use HiEvents\Jobs\Webhook\SecureCallWebhookJob;
use HiEvents\Models\User;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\Webhook\WebhookDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookDispatchServiceQueueingTest extends TestCase
{
    use DatabaseTransactions;

    private int $eventId;

    private int $orderId;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = Str::lower(Str::random(10));
        $now = now()->toDateTimeString();

        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Webhook Organizer',
            'email' => 'organizer-'.$suffix.'@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->eventId = DB::table('events')->insertGetId([
            'short_id' => 'ev'.$suffix,
            'title' => 'Webhook Event',
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'start_date' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->orderId = DB::table('orders')->insertGetId([
            'short_id' => 'os'.$suffix,
            'public_id' => 'OP'.Str::upper($suffix),
            'event_id' => $this->eventId,
            'status' => 'COMPLETED',
            'email' => 'buyer-'.$suffix.'@example.test',
            'first_name' => 'Webhook',
            'last_name' => 'Buyer',
            'currency' => 'USD',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('webhooks')->insert([
            'url' => 'https://receiver.example.test/hook',
            'event_types' => json_encode([DomainEventType::ORDER_CREATED->value]),
            'event_id' => $this->eventId,
            'user_id' => $user->id,
            'account_id' => $accountId,
            'secret' => 'shh-'.$suffix,
            'status' => WebhookStatus::ENABLED->name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_the_outbound_call_is_queued_rather_than_run_inline(): void
    {
        Bus::fake();

        $this->dispatchOrderCreated();

        $this->assertCount(
            0,
            Bus::dispatchedSync(SecureCallWebhookJob::class),
            'dispatchSync() ran the outbound request inline, so the configured tries and '
            .'backoff never applied and a failed delivery was lost rather than retried.'
        );

        $this->assertNotEmpty(
            Bus::dispatched(SecureCallWebhookJob::class),
            'The outbound call must reach the queue so the configured tries and backoff apply.'
        );
    }

    public function test_the_outbound_call_goes_to_the_webhook_queue(): void
    {
        Queue::fake();

        $this->dispatchOrderCreated();

        Queue::assertPushedOn(config('webhook-server.queue'), SecureCallWebhookJob::class);

        $this->assertSame(
            config('queue.webhook_queue_name'),
            config('webhook-server.queue'),
            'Outbound webhook retries on the default queue sit in front of order '
            .'confirmation emails, so they belong on the dedicated webhook queue.'
        );
    }

    public function test_the_queued_call_carries_the_configured_retry_policy(): void
    {
        Queue::fake();

        $this->dispatchOrderCreated();

        $this->assertGreaterThan(1, config('webhook-server.tries'));

        Queue::assertPushed(SecureCallWebhookJob::class, function (SecureCallWebhookJob $job): bool {
            return $job->tries === config('webhook-server.tries');
        });
    }

    public function test_the_occurrence_job_is_queueable_like_every_other_webhook_job(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new DispatchOccurrenceWebhookJob(1, DomainEventType::ORDER_CREATED),
            'Without ShouldQueue the job ran inline on dispatch(), unlike the four sibling '
            .'webhook jobs, so an occurrence webhook blocked its caller.'
        );
    }

    private function dispatchOrderCreated(): void
    {
        $this->app->make(WebhookDispatchService::class)->dispatchOrderWebhook(
            eventType: DomainEventType::ORDER_CREATED,
            orderId: $this->orderId,
        );
    }
}
