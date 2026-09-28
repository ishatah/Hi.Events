<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\BuildingDomainObject;
use HiEvents\Models\Building;
use HiEvents\Repository\Interfaces\BuildingRepositoryInterface;

/**
 * @extends BaseRepository<BuildingDomainObject>
 */
class BuildingRepository extends BaseRepository implements BuildingRepositoryInterface
{
    protected function getModel(): string
    {
        return Building::class;
    }

    public function getDomainObject(): string
    {
        return BuildingDomainObject::class;
    }
}
