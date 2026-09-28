<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Session;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Session\SessionResource;
use HiEvents\Services\Application\Handlers\Session\GetSessionsHandler;
use Illuminate\Http\JsonResponse;

class GetSessionsAction extends BaseAction
{
    public function __construct(
        private readonly GetSessionsHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(SessionResource::class, $this->handler->handle($eventId));
    }
}
