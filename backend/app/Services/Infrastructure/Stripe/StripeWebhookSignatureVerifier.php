<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Stripe;

use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Verifies a Stripe webhook signature against every configured platform secret.
 *
 * Extracted so the HTTP action can verify before it accepts. Verifying inside the queued job
 * meant the endpoint returned 204 to anything, and unsigned payloads consumed queue capacity
 * on the way to being rejected.
 *
 * @see docs/arzo-master-plan/136-master-backlog.md ARZ-306
 */
class StripeWebhookSignatureVerifier
{
    public function __construct(
        private readonly StripeConfigurationService $stripeConfigurationService,
    ) {}

    /**
     * @throws SignatureVerificationException
     */
    public function verify(string $payload, ?string $headerSignature): Event
    {
        if ($headerSignature === null || $headerSignature === '') {
            throw new SignatureVerificationException(__('Missing Stripe signature header'));
        }

        $lastException = null;

        foreach ($this->stripeConfigurationService->getAllWebhookSecrets() as $webhookSecret) {
            if (! $webhookSecret) {
                continue;
            }

            try {
                return Webhook::constructEvent($payload, $headerSignature, $webhookSecret);
            } catch (SignatureVerificationException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException
            ?? new SignatureVerificationException(__('Unable to verify Stripe signature with any platform'));
    }
}
