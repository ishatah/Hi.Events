<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccessPoint extends BaseModel
{
    use SoftDeletes;

    protected $table = 'access_points';

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
