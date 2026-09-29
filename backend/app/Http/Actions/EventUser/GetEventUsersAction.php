<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\EventUser;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\EventUser\EventUserResource;
use HiEvents\Services\Application\Handlers\EventUser\GetEventUsersHandler;
use Illuminate\Http\JsonResponse;

class GetEventUsersAction extends BaseAction
{
    public function __construct(
        private readonly GetEventUsersHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::STAFF_MANAGE);

        return $this->resourceResponse(EventUserResource::class, $this->handler->handle($eventId));
    }
}
