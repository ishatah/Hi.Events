<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Zone;

use HiEvents\DomainObjects\Generated\ZoneDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\ZoneRepositoryInterface;

class DeleteZoneHandler
{
    public function __construct(
        private readonly ZoneRepositoryInterface $repository,
        private readonly GetZoneHandler $getZoneHandler,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $venueId, int $id): void
    {
        $this->getZoneHandler->handle($venueId, $id);

        $this->repository->deleteWhere([
            ZoneDomainObjectAbstract::ID => $id,
            ZoneDomainObjectAbstract::VENUE_ID => $venueId,
        ]);
    }
}
