<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Zone;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Zone\UpsertZoneRequest;
use HiEvents\Resources\Zone\ZoneResource;
use HiEvents\Services\Application\Handlers\Zone\CreateZoneHandler;
use Illuminate\Http\JsonResponse;

class CreateZoneAction extends BaseAction
{
    public function __construct(
        private readonly CreateZoneHandler $handler,
    ) {}

    public function __invoke(UpsertZoneRequest $request, int $venueId): JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($venueId),
            VenueDomainObject::class,
        );

        $record = $this->handler->handle($venueId, $request->validated());

        return $this->resourceResponse(ZoneResource::class, $record, statusCode: 201);
    }

    private function resolveVenueId(int $venueId): int
    {
        return $venueId;
    }
}
