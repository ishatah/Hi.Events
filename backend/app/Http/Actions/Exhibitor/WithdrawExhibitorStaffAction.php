<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Exhibitor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Exhibitor\ExhibitorStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Validation\ValidationException;

class WithdrawExhibitorStaffAction extends BaseAction
{
    public function __construct(
        private readonly ExhibitorStaffService $exhibitorStaffService,
    ) {}

    public function __invoke(int $eventId, int $exhibitorStaffId): LaravelResponse|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::EXHIBITOR_MANAGE);

        try {
            $this->exhibitorStaffService->withdrawStaff($exhibitorStaffId);
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['id' => $exception->getMessage()]);
        }

        return $this->deletedResponse();
    }
}
