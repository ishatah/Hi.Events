<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccessPoint;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\AccessPoint\AccessPointResource;
use HiEvents\Services\Application\Handlers\AccessPoint\GetAccessPointsHandler;
use HiEvents\Services\Domain\Space\ZoneScopeService;
use Illuminate\Http\JsonResponse;

class GetAccessPointsAction extends BaseAction
{
    public function __construct(
        private readonly GetAccessPointsHandler $handler,
        private readonly ZoneScopeService $zoneScopeService,
    ) {}

    public function __invoke(int $zoneId): JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($zoneId),
            VenueDomainObject::class,
        );

        return $this->resourceResponse(AccessPointResource::class, $this->handler->handle($zoneId));
    }

    private function resolveVenueId(int $zoneId): int
    {
        return $this->zoneScopeService->venueIdForZone($zoneId);
    }
}
