<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Session;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Session\SessionResource;
use HiEvents\Services\Application\Handlers\Session\GetSessionHandler;
use Illuminate\Http\JsonResponse;

class GetSessionAction extends BaseAction
{
    public function __construct(
        private readonly GetSessionHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $id): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(SessionResource::class, $this->handler->handle($eventId, $id));
    }
}
