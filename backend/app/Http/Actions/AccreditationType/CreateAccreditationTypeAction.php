<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccreditationType;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\AccreditationType\UpsertAccreditationTypeRequest;
use HiEvents\Resources\AccreditationType\AccreditationTypeResource;
use HiEvents\Services\Application\Handlers\AccreditationType\CreateAccreditationTypeHandler;
use Illuminate\Http\JsonResponse;

class CreateAccreditationTypeAction extends BaseAction
{
    public function __construct(
        private readonly CreateAccreditationTypeHandler $handler,
    ) {}

    public function __invoke(UpsertAccreditationTypeRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $record = $this->handler->handle($eventId, $request->validated());

        return $this->resourceResponse(AccreditationTypeResource::class, $record, statusCode: 201);
    }
}
