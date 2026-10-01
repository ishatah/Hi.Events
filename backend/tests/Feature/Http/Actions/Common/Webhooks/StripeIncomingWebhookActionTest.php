<?php

namespace Tests\Feature\Http\Actions\Common\Webhooks;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StripeIncomingWebhookActionTest extends TestCase
{
    use DatabaseTransactions;

    private const PAYLOAD = '{"id":"evt_test","type":"payment_intent.succeeded","data":{"object":{"id":"pi_test"}}}';

    public function test_an_unsigned_webhook_is_rejected(): void
    {
        Bus::fake();

        $response = $this->call(
            'POST',
            '/public/webhooks/stripe',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: self::PAYLOAD,
        );

        $response->assertStatus(400);

        Bus::assertNothingDispatched();
    }

    public function test_a_webhook_with_a_bogus_signature_is_rejected(): void
    {
        Bus::fake();

        $response = $this->call(
            'POST',
            '/public/webhooks/stripe',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=1,v1=not-a-real-signature',
            ],
            content: self::PAYLOAD,
        );

        $response->assertStatus(400);

        Bus::assertNothingDispatched();
    }

    public function test_the_rejection_does_not_log_the_raw_payload(): void
    {
        $logged = [];

        Log::listen(function ($message) use (&$logged): void {
            $logged[] = json_encode($message->context ?? []).' '.$message->message;
        });

        $this->call(
            'POST',
            '/public/webhooks/stripe',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: self::PAYLOAD,
        );

        foreach ($logged as $entry) {
            $this->assertStringNotContainsString(
                'pi_test',
                $entry,
                'A Stripe payload carries customer names, emails and addresses, so logging it '
                .'in full writes that into the log store wholesale.'
            );
        }
    }
}
