<?php

namespace HiEvents\Http\Actions\Common\Webhooks;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\DTO\StripeWebhookDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\IncomingWebhookHandler;
use HiEvents\Services\Infrastructure\Stripe\StripeWebhookSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Throwable;

class StripeIncomingWebhookAction extends BaseAction
{
    public function __construct(
        private readonly StripeWebhookSignatureVerifier $signatureVerifier,
    ) {}

    public function __invoke(Request $request): Response
    {
        $headerSignature = $request->server('HTTP_STRIPE_SIGNATURE');
        $payload = $request->getContent();

        try {
            $event = $this->signatureVerifier->verify($payload, $headerSignature);
        } catch (SignatureVerificationException $exception) {
            logger()->warning('Rejected an unverified Stripe webhook', [
                'reason' => $exception->getMessage(),
                'ip' => $this->getClientIp($request),
            ]);

            return $this->noContentResponse(ResponseCodes::HTTP_BAD_REQUEST);
        }

        try {
            dispatch(static function (IncomingWebhookHandler $handler) use ($headerSignature, $payload) {
                $handler->handle(new StripeWebhookDTO(
                    headerSignature: $headerSignature,
                    payload: $payload,
                ));
            })->catch(function (Throwable $exception) use ($event) {
                logger()->error('Failed to handle incoming Stripe webhook', [
                    'reason' => $exception->getMessage(),
                    'event_id' => $event->id,
                    'event_type' => $event->type,
                ]);
            });
        } catch (Throwable $exception) {
            logger()->error('Could not queue a verified Stripe webhook', [
                'reason' => $exception->getMessage(),
                'event_id' => $event->id,
            ]);

            return $this->noContentResponse(ResponseCodes::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->noContentResponse();
    }
}
