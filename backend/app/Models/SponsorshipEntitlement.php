<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorshipEntitlement extends BaseModel
{
    protected $table = 'sponsorship_entitlements';

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class);
    }
}
