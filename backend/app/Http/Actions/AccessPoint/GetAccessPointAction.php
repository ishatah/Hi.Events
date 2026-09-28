<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccessPoint;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\AccessPoint\AccessPointResource;
use HiEvents\Services\Application\Handlers\AccessPoint\GetAccessPointHandler;
use HiEvents\Services\Domain\Space\ZoneScopeService;
use Illuminate\Http\JsonResponse;

class GetAccessPointAction extends BaseAction
{
    public function __construct(
        private readonly GetAccessPointHandler $handler,
        private readonly ZoneScopeService $zoneScopeService,
    ) {}

    public function __invoke(int $zoneId, int $id): JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($zoneId),
            VenueDomainObject::class,
        );

        return $this->resourceResponse(AccessPointResource::class, $this->handler->handle($zoneId, $id));
    }

    private function resolveVenueId(int $zoneId): int
    {
        return $this->zoneScopeService->venueIdForZone($zoneId);
    }
}
