<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsvpResponse extends BaseModel
{
    protected $table = 'rsvp_responses';

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
