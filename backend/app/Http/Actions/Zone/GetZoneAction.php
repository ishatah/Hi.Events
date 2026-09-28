<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Zone;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Zone\ZoneResource;
use HiEvents\Services\Application\Handlers\Zone\GetZoneHandler;
use Illuminate\Http\JsonResponse;

class GetZoneAction extends BaseAction
{
    public function __construct(
        private readonly GetZoneHandler $handler,
    ) {}

    public function __invoke(int $venueId, int $id): JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($venueId),
            VenueDomainObject::class,
        );

        return $this->resourceResponse(ZoneResource::class, $this->handler->handle($venueId, $id));
    }

    private function resolveVenueId(int $venueId): int
    {
        return $venueId;
    }
}
