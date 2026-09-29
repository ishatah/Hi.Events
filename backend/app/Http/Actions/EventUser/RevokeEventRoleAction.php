<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\EventUser;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\EventUser\RevokeEventRoleHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as LaravelResponse;

class RevokeEventRoleAction extends BaseAction
{
    public function __construct(
        private readonly RevokeEventRoleHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $userId): LaravelResponse|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::STAFF_MANAGE);

        $this->handler->handle($eventId, $userId);

        return $this->deletedResponse();
    }
}
