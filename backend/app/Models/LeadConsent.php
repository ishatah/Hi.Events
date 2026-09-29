<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadConsent extends BaseModel
{
    protected $table = 'lead_consents';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function eventExhibitor(): BelongsTo
    {
        return $this->belongsTo(EventExhibitor::class);
    }
}
