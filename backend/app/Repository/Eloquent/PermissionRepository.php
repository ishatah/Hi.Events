<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\PermissionDomainObject;
use HiEvents\Models\Permission;
use HiEvents\Repository\Interfaces\PermissionRepositoryInterface;

/**
 * @extends BaseRepository<PermissionDomainObject>
 */
class PermissionRepository extends BaseRepository implements PermissionRepositoryInterface
{
    protected function getModel(): string
    {
        return Permission::class;
    }

    public function getDomainObject(): string
    {
        return PermissionDomainObject::class;
    }
}
