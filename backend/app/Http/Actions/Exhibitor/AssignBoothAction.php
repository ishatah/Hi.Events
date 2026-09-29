<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Exhibitor;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Exhibitor\AssignBoothRequest;
use HiEvents\Services\Domain\Exhibitor\BoothAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AssignBoothAction extends BaseAction
{
    public function __construct(
        private readonly BoothAssignmentService $boothAssignmentService,
    ) {}

    public function __invoke(AssignBoothRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::EXHIBITOR_MANAGE);

        $heldUntil = $request->validated('held_until');
        $exhibitorId = $request->validated('event_exhibitor_id');

        try {
            // A deadline means a hold; without one it is a firm assignment, which needs
            // somebody to assign it to.
            if ($heldUntil !== null) {
                $assignmentId = $this->boothAssignmentService->hold(
                    eventId: $eventId,
                    boothId: (int) $request->validated('booth_id'),
                    eventExhibitorId: $exhibitorId !== null ? (int) $exhibitorId : null,
                    until: Carbon::parse((string) $heldUntil),
                    actorUserId: $this->getAuthenticatedUser()->getId(),
                );
            } else {
                if ($exhibitorId === null) {
                    throw new ResourceConflictException(
                        __('Assigning a booth requires an exhibitor.')
                    );
                }

                $assignmentId = $this->boothAssignmentService->assign(
                    eventId: $eventId,
                    boothId: (int) $request->validated('booth_id'),
                    eventExhibitorId: (int) $exhibitorId,
                    actorUserId: $this->getAuthenticatedUser()->getId(),
                    role: (string) ($request->validated('role') ?? 'PRIMARY'),
                );
            }
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['booth_id' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['id' => $assignmentId]);
    }
}
