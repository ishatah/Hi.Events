<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Zone;

use HiEvents\DomainObjects\Generated\ZoneDomainObjectAbstract;
use HiEvents\Repository\Interfaces\ZoneRepositoryInterface;
use Illuminate\Support\Collection;

class GetZonesHandler
{
    public function __construct(
        private readonly ZoneRepositoryInterface $repository,
    ) {}

    public function handle(int $venueId): Collection
    {
        return $this->repository->findWhere([
            ZoneDomainObjectAbstract::VENUE_ID => $venueId,
        ]);
    }
}
