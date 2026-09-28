<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccessPoint;

use HiEvents\DomainObjects\Generated\AccessPointDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AccessPointRepositoryInterface;

class DeleteAccessPointHandler
{
    public function __construct(
        private readonly AccessPointRepositoryInterface $repository,
        private readonly GetAccessPointHandler $getAccessPointHandler,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $zoneId, int $id): void
    {
        $this->getAccessPointHandler->handle($zoneId, $id);

        $this->repository->deleteWhere([
            AccessPointDomainObjectAbstract::ID => $id,
            AccessPointDomainObjectAbstract::ZONE_ID => $zoneId,
        ]);
    }
}
