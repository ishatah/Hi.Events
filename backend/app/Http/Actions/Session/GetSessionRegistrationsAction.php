<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Session;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Session\SessionRegistrationResource;
use HiEvents\Services\Application\Handlers\Session\GetSessionRegistrationsHandler;
use Illuminate\Http\JsonResponse;

class GetSessionRegistrationsAction extends BaseAction
{
    public function __construct(
        private readonly GetSessionRegistrationsHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $sessionId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            SessionRegistrationResource::class,
            $this->handler->handle($sessionId)
        );
    }
}
