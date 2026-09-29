<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionRole extends BaseModel
{
    protected $table = 'permission_roles';

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
