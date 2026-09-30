<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SponsorshipPackage extends BaseModel
{
    use SoftDeletes;

    protected $table = 'sponsorship_packages';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class, 'sponsorship_package_id');
    }
}
