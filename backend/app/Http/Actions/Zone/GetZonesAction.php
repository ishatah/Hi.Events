<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Zone;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Zone\ZoneResource;
use HiEvents\Services\Application\Handlers\Zone\GetZonesHandler;
use Illuminate\Http\JsonResponse;

class GetZonesAction extends BaseAction
{
    public function __construct(
        private readonly GetZonesHandler $handler,
    ) {}

    public function __invoke(int $venueId): JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($venueId),
            VenueDomainObject::class,
        );

        return $this->resourceResponse(ZoneResource::class, $this->handler->handle($venueId));
    }

    private function resolveVenueId(int $venueId): int
    {
        return $venueId;
    }
}
