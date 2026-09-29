<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accreditation;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Accreditation\RejectAccreditationRequest;
use HiEvents\Services\Application\Handlers\Accreditation\RejectAccreditationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RejectAccreditationAction extends BaseAction
{
    public function __construct(
        private readonly RejectAccreditationHandler $handler,
    ) {}

    public function __invoke(
        RejectAccreditationRequest $request,
        int $eventId,
        int $accreditationId
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::ACCREDITATION_REJECT);

        try {
            $this->handler->handle(
                accreditationId: $accreditationId,
                actorUserId: $this->getAuthenticatedUser()->getId(),
                reason: (string) $request->validated('reason'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['status' => 'REJECTED']);
    }
}
