<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Zone;

use HiEvents\DomainObjects\Generated\ZoneDomainObjectAbstract;
use HiEvents\DomainObjects\ZoneDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\ZoneRepositoryInterface;

class GetZoneHandler
{
    public function __construct(
        private readonly ZoneRepositoryInterface $repository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $venueId, int $id): ZoneDomainObject
    {
        $record = $this->repository->findFirstWhere([
            ZoneDomainObjectAbstract::ID => $id,
            ZoneDomainObjectAbstract::VENUE_ID => $venueId,
        ]);

        if ($record === null) {
            throw new ResourceNotFoundException(__('The requested record could not be found.'));
        }

        return $record;
    }
}
