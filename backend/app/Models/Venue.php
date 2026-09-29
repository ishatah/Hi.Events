<?php

namespace HiEvents\Models;

use HiEvents\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends BaseModel
{
    use BelongsToTenant;
    use SoftDeletes;

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
