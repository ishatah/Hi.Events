<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\PermissionRoleDomainObject;
use HiEvents\Models\PermissionRole;
use HiEvents\Repository\Interfaces\PermissionRoleRepositoryInterface;

/**
 * @extends BaseRepository<PermissionRoleDomainObject>
 */
class PermissionRoleRepository extends BaseRepository implements PermissionRoleRepositoryInterface
{
    protected function getModel(): string
    {
        return PermissionRole::class;
    }

    public function getDomainObject(): string
    {
        return PermissionRoleDomainObject::class;
    }
}
