<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Notification;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Notification\RegisterPushSubscriptionRequest;
use HiEvents\Services\Domain\Notification\PushSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Registers the calling user's own device for push.
 *
 * Scoped to the authenticated user rather than taking a subscriber id, because a request that
 * names who it is subscribing could sign somebody else's phone up to somebody else's alerts.
 * Attendee and ops-device subscriptions arrive through their own authenticated routes.
 */
class RegisterPushSubscriptionAction extends BaseAction
{
    public function __construct(
        private readonly PushSubscriptionService $pushSubscriptionService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(RegisterPushSubscriptionRequest $request): JsonResponse
    {
        $userId = $this->getAuthenticatedUser()->getId();
        $platform = $request->validated('platform');
        $eventId = $request->input('event_id') !== null ? (int) $request->input('event_id') : null;

        if ($eventId !== null) {
            $this->isActionAuthorized($eventId, EventDomainObject::class);
        }

        try {
            $subscriptionId = $platform === 'WEB'
                ? $this->pushSubscriptionService->registerWeb(
                    subscriberType: 'USER',
                    subscriberId: $userId,
                    endpoint: $request->validated('endpoint'),
                    p256dh: $request->input('keys.p256dh'),
                    auth: $request->input('keys.auth'),
                    eventId: $eventId,
                    userAgent: $request->userAgent(),
                    locale: (string) ($request->input('locale') ?? 'en'),
                )
                : $this->pushSubscriptionService->registerNative(
                    subscriberType: 'USER',
                    subscriberId: $userId,
                    platform: $platform,
                    token: $request->validated('token'),
                    eventId: $eventId,
                    locale: (string) ($request->input('locale') ?? 'en'),
                );

            if ($request->input('declined_categories') !== null) {
                $this->pushSubscriptionService->setCategoryOptOuts(
                    $subscriptionId,
                    $request->input('declined_categories')
                );
            }
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['platform' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['id' => $subscriptionId], statusCode: 201);
    }
}
