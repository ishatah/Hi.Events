<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Exhibitor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Exhibitor\NameExhibitorStaffRequest;
use HiEvents\Services\Domain\Exhibitor\ExhibitorStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class NameExhibitorStaffAction extends BaseAction
{
    public function __construct(
        private readonly ExhibitorStaffService $exhibitorStaffService,
    ) {}

    public function __invoke(
        NameExhibitorStaffRequest $request,
        int $eventId,
        int $eventExhibitorId
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::EXHIBITOR_MANAGE);

        try {
            $staffId = $this->exhibitorStaffService->nameStaff(
                eventExhibitorId: $eventExhibitorId,
                personId: (int) $request->validated('person_id'),
                role: (string) ($request->validated('role') ?? 'STAFF'),
                actorUserId: $this->getAuthenticatedUser()->getId(),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['person_id' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['id' => $staffId]);
    }
}
