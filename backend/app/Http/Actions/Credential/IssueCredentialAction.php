<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Credential;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Credential\IssueCredentialRequest;
use HiEvents\Resources\Credential\CredentialResource;
use HiEvents\Services\Application\Handlers\Credential\IssueCredentialHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class IssueCredentialAction extends BaseAction
{
    public function __construct(
        private readonly IssueCredentialHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(IssueCredentialRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $credential = $this->handler->handle($eventId, $request->validated());
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages([
                'accreditation_id' => $exception->getMessage(),
            ]);
        }

        return $this->resourceResponse(CredentialResource::class, $credential, statusCode: 201);
    }
}
