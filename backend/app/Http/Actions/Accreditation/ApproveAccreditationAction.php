<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accreditation;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Accreditation\ApproveAccreditationRequest;
use HiEvents\Services\Application\Handlers\Accreditation\ApproveAccreditationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ApproveAccreditationAction extends BaseAction
{
    public function __construct(
        private readonly ApproveAccreditationHandler $handler,
    ) {}

    public function __invoke(
        ApproveAccreditationRequest $request,
        int $eventId,
        int $accreditationId
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::ACCREDITATION_APPROVE);

        try {
            $this->handler->handle(
                accreditationId: $accreditationId,
                actorUserId: $this->getAuthenticatedUser()->getId(),
                approvedZoneIds: $request->validated('approved_zones'),
                notes: $request->validated('notes'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['status' => 'APPROVED']);
    }
}
