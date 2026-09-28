<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccreditationType;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\AccreditationType\AccreditationTypeResource;
use HiEvents\Services\Application\Handlers\AccreditationType\GetAccreditationTypesHandler;
use Illuminate\Http\JsonResponse;

class GetAccreditationTypesAction extends BaseAction
{
    public function __construct(
        private readonly GetAccreditationTypesHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(AccreditationTypeResource::class, $this->handler->handle($eventId));
    }
}
