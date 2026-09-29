<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\PermissionRolePermissionDomainObject;
use HiEvents\Models\PermissionRolePermission;
use HiEvents\Repository\Interfaces\PermissionRolePermissionRepositoryInterface;

/**
 * @extends BaseRepository<PermissionRolePermissionDomainObject>
 */
class PermissionRolePermissionRepository extends BaseRepository implements PermissionRolePermissionRepositoryInterface
{
    protected function getModel(): string
    {
        return PermissionRolePermission::class;
    }

    public function getDomainObject(): string
    {
        return PermissionRolePermissionDomainObject::class;
    }
}
