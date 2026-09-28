<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccessPoint;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\AccessPoint\UpsertAccessPointRequest;
use HiEvents\Resources\AccessPoint\AccessPointResource;
use HiEvents\Services\Application\Handlers\AccessPoint\CreateAccessPointHandler;
use HiEvents\Services\Domain\Space\ZoneScopeService;
use Illuminate\Http\JsonResponse;

class CreateAccessPointAction extends BaseAction
{
    public function __construct(
        private readonly CreateAccessPointHandler $handler,
        private readonly ZoneScopeService $zoneScopeService,
    ) {}

    public function __invoke(UpsertAccessPointRequest $request, int $zoneId): JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($zoneId),
            VenueDomainObject::class,
        );

        $record = $this->handler->handle($zoneId, $request->validated());

        return $this->resourceResponse(AccessPointResource::class, $record, statusCode: 201);
    }

    private function resolveVenueId(int $zoneId): int
    {
        return $this->zoneScopeService->venueIdForZone($zoneId);
    }
}
