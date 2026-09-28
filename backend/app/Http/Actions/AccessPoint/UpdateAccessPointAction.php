<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccessPoint;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\AccessPoint\UpsertAccessPointRequest;
use HiEvents\Resources\AccessPoint\AccessPointResource;
use HiEvents\Services\Application\Handlers\AccessPoint\UpdateAccessPointHandler;
use HiEvents\Services\Domain\Space\ZoneScopeService;
use Illuminate\Http\JsonResponse;

class UpdateAccessPointAction extends BaseAction
{
    public function __construct(
        private readonly UpdateAccessPointHandler $handler,
        private readonly ZoneScopeService $zoneScopeService,
    ) {}

    public function __invoke(UpsertAccessPointRequest $request, int $zoneId, int $id): JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($zoneId),
            VenueDomainObject::class,
        );

        $record = $this->handler->handle($zoneId, $id, $request->validated());

        return $this->resourceResponse(AccessPointResource::class, $record);
    }

    private function resolveVenueId(int $zoneId): int
    {
        return $this->zoneScopeService->venueIdForZone($zoneId);
    }
}
