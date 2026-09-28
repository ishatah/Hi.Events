<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Session;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Session\UpsertSessionRequest;
use HiEvents\Resources\Session\SessionResource;
use HiEvents\Services\Application\Handlers\Session\CreateSessionHandler;
use Illuminate\Http\JsonResponse;

class CreateSessionAction extends BaseAction
{
    public function __construct(
        private readonly CreateSessionHandler $handler,
    ) {}

    public function __invoke(UpsertSessionRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $record = $this->handler->handle($eventId, $request->validated());

        return $this->resourceResponse(SessionResource::class, $record, statusCode: 201);
    }
}
