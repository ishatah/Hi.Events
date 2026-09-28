<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccessPointDomainObject;
use HiEvents\Models\AccessPoint;
use HiEvents\Repository\Interfaces\AccessPointRepositoryInterface;

/**
 * @extends BaseRepository<AccessPointDomainObject>
 */
class AccessPointRepository extends BaseRepository implements AccessPointRepositoryInterface
{
    protected function getModel(): string
    {
        return AccessPoint::class;
    }

    public function getDomainObject(): string
    {
        return AccessPointDomainObject::class;
    }
}
