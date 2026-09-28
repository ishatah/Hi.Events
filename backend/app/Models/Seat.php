<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seat extends BaseModel
{
    use SoftDeletes;

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
