<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccreditationType;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\AccreditationType\UpsertAccreditationTypeRequest;
use HiEvents\Resources\AccreditationType\AccreditationTypeResource;
use HiEvents\Services\Application\Handlers\AccreditationType\UpdateAccreditationTypeHandler;
use Illuminate\Http\JsonResponse;

class UpdateAccreditationTypeAction extends BaseAction
{
    public function __construct(
        private readonly UpdateAccreditationTypeHandler $handler,
    ) {}

    public function __invoke(UpsertAccreditationTypeRequest $request, int $eventId, int $id): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $record = $this->handler->handle($eventId, $id, $request->validated());

        return $this->resourceResponse(AccreditationTypeResource::class, $record);
    }
}
