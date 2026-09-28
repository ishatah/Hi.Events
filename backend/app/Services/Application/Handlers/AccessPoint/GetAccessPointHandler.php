<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccessPoint;

use HiEvents\DomainObjects\AccessPointDomainObject;
use HiEvents\DomainObjects\Generated\AccessPointDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AccessPointRepositoryInterface;

class GetAccessPointHandler
{
    public function __construct(
        private readonly AccessPointRepositoryInterface $repository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $zoneId, int $id): AccessPointDomainObject
    {
        $record = $this->repository->findFirstWhere([
            AccessPointDomainObjectAbstract::ID => $id,
            AccessPointDomainObjectAbstract::ZONE_ID => $zoneId,
        ]);

        if ($record === null) {
            throw new ResourceNotFoundException(__('The requested record could not be found.'));
        }

        return $record;
    }
}
