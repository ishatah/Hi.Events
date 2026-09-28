<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccessGrantDomainObject;
use HiEvents\Models\AccessGrant;
use HiEvents\Repository\Interfaces\AccessGrantRepositoryInterface;

/**
 * @extends BaseRepository<AccessGrantDomainObject>
 */
class AccessGrantRepository extends BaseRepository implements AccessGrantRepositoryInterface
{
    protected function getModel(): string
    {
        return AccessGrant::class;
    }

    public function getDomainObject(): string
    {
        return AccessGrantDomainObject::class;
    }
}
