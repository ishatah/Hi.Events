<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Device;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Device\RegisterDeviceRequest;
use HiEvents\Services\Domain\Device\DeviceEnrolmentService;
use Illuminate\Http\JsonResponse;

class RegisterDeviceAction extends BaseAction
{
    public function __construct(
        private readonly DeviceEnrolmentService $enrolment,
    ) {}

    public function __invoke(RegisterDeviceRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::DEVICE_MANAGE);

        $registered = $this->enrolment->register(
            accountId: $this->getAuthenticatedAccountId(),
            name: (string) $request->validated('name'),
            deviceType: (string) $request->validated('device_type'),
            eventId: $eventId,
            accessPointId: $request->validated('access_point_id') !== null
                ? (int) $request->validated('access_point_id')
                : null,
        );

        // The code is shown to the operator to type into the device, and expires shortly.
        return $this->jsonResponse([
            'device_id' => $registered['device_id'],
            'pairing_code' => $registered['pairing_code'],
            'expires_at' => $registered['expires_at']->toIso8601String(),
        ]);
    }
}
