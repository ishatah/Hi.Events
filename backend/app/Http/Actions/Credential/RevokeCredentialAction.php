<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Credential;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Credential\RevokeCredentialRequest;
use HiEvents\Services\Application\Handlers\Credential\RevokeCredentialHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RevokeCredentialAction extends BaseAction
{
    public function __construct(
        private readonly RevokeCredentialHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(RevokeCredentialRequest $request, int $eventId, int $id): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->handler->handle(
                eventId: $eventId,
                credentialId: $id,
                userId: $this->getAuthenticatedUser()->getId(),
                reason: (string) $request->validated('reason'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['id' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['revoked' => true]);
    }
}
