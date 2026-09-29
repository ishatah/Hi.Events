<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Device;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Device\DeviceEnrolmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Validation\ValidationException;

class SuspendDeviceAction extends BaseAction
{
    public function __construct(
        private readonly DeviceEnrolmentService $enrolment,
    ) {}

    public function __invoke(int $eventId, int $deviceId): LaravelResponse|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::DEVICE_MANAGE);

        try {
            $this->enrolment->suspend($deviceId, $this->getAuthenticatedAccountId());
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['id' => $exception->getMessage()]);
        }

        return $this->deletedResponse();
    }
}
