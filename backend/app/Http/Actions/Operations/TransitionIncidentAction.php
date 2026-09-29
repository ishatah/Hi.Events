<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Operations;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\IncidentStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Operations\TransitionIncidentRequest;
use HiEvents\Services\Domain\Operations\IncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TransitionIncidentAction extends BaseAction
{
    public function __construct(
        private readonly IncidentService $incidentService,
    ) {}

    public function __invoke(
        TransitionIncidentRequest $request,
        int $eventId,
        int $incidentId
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::INCIDENT_MANAGE);

        try {
            $this->incidentService->transition(
                incidentId: $incidentId,
                to: IncidentStatus::from((string) $request->validated('status')),
                actorUserId: $this->getAuthenticatedUser()->getId(),
                note: $request->validated('note'),
                resolution: $request->validated('resolution'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['status' => $request->validated('status')]);
    }
}
