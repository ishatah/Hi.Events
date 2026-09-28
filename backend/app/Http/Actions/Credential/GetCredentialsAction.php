<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Credential;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Credential\CredentialResource;
use HiEvents\Services\Application\Handlers\Credential\GetCredentialsHandler;
use Illuminate\Http\JsonResponse;

class GetCredentialsAction extends BaseAction
{
    public function __construct(
        private readonly GetCredentialsHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(CredentialResource::class, $this->handler->handle($eventId));
    }
}
