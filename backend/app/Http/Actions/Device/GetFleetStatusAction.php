<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Device;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Device\DeviceFleetService;
use Illuminate\Http\JsonResponse;

class GetFleetStatusAction extends BaseAction
{
    public function __construct(
        private readonly DeviceFleetService $fleet,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::DEVICE_MANAGE);

        return $this->jsonResponse($this->fleet->fleetStatus($eventId));
    }
}
