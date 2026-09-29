<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accreditation;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Accreditation\IssueAccreditationCredentialHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class IssueAccreditationCredentialAction extends BaseAction
{
    public function __construct(
        private readonly IssueAccreditationCredentialHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $accreditationId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::CREDENTIAL_ISSUE);

        try {
            $credentialId = $this->handler->handle(
                $accreditationId,
                $this->getAuthenticatedUser()->getId()
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['credential_id' => $credentialId]);
    }
}
