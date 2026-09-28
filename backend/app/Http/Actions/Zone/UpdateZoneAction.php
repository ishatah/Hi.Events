<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Zone;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Zone\UpsertZoneRequest;
use HiEvents\Resources\Zone\ZoneResource;
use HiEvents\Services\Application\Handlers\Zone\UpdateZoneHandler;
use Illuminate\Http\JsonResponse;

class UpdateZoneAction extends BaseAction
{
    public function __construct(
        private readonly UpdateZoneHandler $handler,
    ) {}

    public function __invoke(UpsertZoneRequest $request, int $venueId, int $id): JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($venueId),
            VenueDomainObject::class,
        );

        $record = $this->handler->handle($venueId, $id, $request->validated());

        return $this->resourceResponse(ZoneResource::class, $record);
    }

    private function resolveVenueId(int $venueId): int
    {
        return $venueId;
    }
}
