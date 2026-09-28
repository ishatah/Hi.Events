<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends BaseModel
{
    use SoftDeletes;

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function parent_zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'parent_zone_id');
    }

    public function access_points(): HasMany
    {
        return $this->hasMany(AccessPoint::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
