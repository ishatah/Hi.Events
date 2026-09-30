<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Notification;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Notification\PushSubscriptionService;
use Illuminate\Http\JsonResponse;

/**
 * Turns push off for the calling user, across every device they registered.
 *
 * Scoped to the authenticated user and takes no id, so nobody can silence somebody else's
 * alerts — which for staff would mean a missed incident.
 */
class RevokePushSubscriptionAction extends BaseAction
{
    public function __construct(
        private readonly PushSubscriptionService $pushSubscriptionService,
    ) {}

    public function __invoke(): JsonResponse
    {
        $revoked = $this->pushSubscriptionService->revokeAllFor(
            'USER',
            $this->getAuthenticatedUser()->getId()
        );

        return $this->jsonResponse(['revoked' => $revoked]);
    }
}
