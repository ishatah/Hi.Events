<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccessRule extends BaseModel
{
    use SoftDeletes;

    protected $table = 'access_rules';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
