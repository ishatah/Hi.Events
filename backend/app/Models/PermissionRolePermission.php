<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionRolePermission extends BaseModel
{
    protected $table = 'permission_role_permissions';

    public function permissionRole(): BelongsTo
    {
        return $this->belongsTo(PermissionRole::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
