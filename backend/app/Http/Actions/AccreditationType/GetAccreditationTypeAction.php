<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccreditationType;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\AccreditationType\AccreditationTypeResource;
use HiEvents\Services\Application\Handlers\AccreditationType\GetAccreditationTypeHandler;
use Illuminate\Http\JsonResponse;

class GetAccreditationTypeAction extends BaseAction
{
    public function __construct(
        private readonly GetAccreditationTypeHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $id): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(AccreditationTypeResource::class, $this->handler->handle($eventId, $id));
    }
}
