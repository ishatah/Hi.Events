<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\FloorDomainObject;
use HiEvents\Models\Floor;
use HiEvents\Repository\Interfaces\FloorRepositoryInterface;

/**
 * @extends BaseRepository<FloorDomainObject>
 */
class FloorRepository extends BaseRepository implements FloorRepositoryInterface
{
    protected function getModel(): string
    {
        return Floor::class;
    }

    public function getDomainObject(): string
    {
        return FloorDomainObject::class;
    }
}
