<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Operations;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Staffing\ShiftAssignmentService;
use Illuminate\Http\JsonResponse;

class GetStaffingGapsAction extends BaseAction
{
    public function __construct(
        private readonly ShiftAssignmentService $shiftAssignmentService,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::STAFF_MANAGE);

        return $this->jsonResponse([
            'understaffed_shifts' => $this->shiftAssignmentService->understaffedShifts($eventId),
        ]);
    }
}
