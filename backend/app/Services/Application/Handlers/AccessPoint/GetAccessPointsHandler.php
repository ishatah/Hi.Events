<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccessPoint;

use HiEvents\DomainObjects\Generated\AccessPointDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AccessPointRepositoryInterface;
use Illuminate\Support\Collection;

class GetAccessPointsHandler
{
    public function __construct(
        private readonly AccessPointRepositoryInterface $repository,
    ) {}

    public function handle(int $zoneId): Collection
    {
        return $this->repository->findWhere([
            AccessPointDomainObjectAbstract::ZONE_ID => $zoneId,
        ]);
    }
}
