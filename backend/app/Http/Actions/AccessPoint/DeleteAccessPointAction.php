<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccessPoint;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\AccessPoint\DeleteAccessPointHandler;
use HiEvents\Services\Domain\Space\ZoneScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteAccessPointAction extends BaseAction
{
    public function __construct(
        private readonly DeleteAccessPointHandler $handler,
        private readonly ZoneScopeService $zoneScopeService,
    ) {}

    public function __invoke(int $zoneId, int $id): Response|JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($zoneId),
            VenueDomainObject::class,
        );

        $this->handler->handle($zoneId, $id);

        return $this->deletedResponse();
    }

    private function resolveVenueId(int $zoneId): int
    {
        return $this->zoneScopeService->venueIdForZone($zoneId);
    }
}
