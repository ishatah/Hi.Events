<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Session;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Session\UpsertSessionRequest;
use HiEvents\Resources\Session\SessionResource;
use HiEvents\Services\Application\Handlers\Session\UpdateSessionHandler;
use Illuminate\Http\JsonResponse;

class UpdateSessionAction extends BaseAction
{
    public function __construct(
        private readonly UpdateSessionHandler $handler,
    ) {}

    public function __invoke(UpsertSessionRequest $request, int $eventId, int $id): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $record = $this->handler->handle($eventId, $id, $request->validated());

        return $this->resourceResponse(SessionResource::class, $record);
    }
}
