<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Notification;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Notification\PushSubscriptionService;
use Illuminate\Http\JsonResponse;

/**
 * How many devices an event can actually reach.
 *
 * The staff figure is the one that matters operationally: attendee push on iPhone only reaches
 * people who installed the PWA and then opted in, so a low attendee number is expected rather
 * than a fault, while a low staff number means an incident alert may not arrive.
 */
class GetPushHealthAction extends BaseAction
{
    public function __construct(
        private readonly PushSubscriptionService $pushSubscriptionService,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::ACCESS_LOGS_VIEW);

        return $this->jsonResponse($this->pushSubscriptionService->healthFor($eventId));
    }
}
