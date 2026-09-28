<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends BaseModel
{
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function badge_template(): BelongsTo
    {
        return $this->belongsTo(BadgeTemplate::class);
    }

    public function badge_print_jobs(): HasMany
    {
        return $this->hasMany(BadgePrintJob::class);
    }
}
