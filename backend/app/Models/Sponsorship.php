<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sponsorship extends BaseModel
{
    use SoftDeletes;

    protected $table = 'sponsorships';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SponsorshipPackage::class, 'sponsorship_package_id');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(SponsorshipEntitlement::class);
    }
}
