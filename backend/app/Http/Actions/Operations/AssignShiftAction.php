<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Operations;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Operations\AssignShiftRequest;
use HiEvents\Services\Domain\Staffing\ShiftAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AssignShiftAction extends BaseAction
{
    public function __construct(
        private readonly ShiftAssignmentService $shiftAssignmentService,
    ) {}

    public function __invoke(AssignShiftRequest $request, int $eventId, int $shiftId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::STAFF_MANAGE);

        try {
            $assignmentId = $this->shiftAssignmentService->assign(
                shiftId: $shiftId,
                personId: (int) $request->validated('person_id'),
                accreditationId: $request->validated('accreditation_id') !== null
                    ? (int) $request->validated('accreditation_id')
                    : null,
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['person_id' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['id' => $assignmentId]);
    }
}
