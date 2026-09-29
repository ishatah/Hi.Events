<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoothAssignment extends BaseModel
{
    protected $table = 'booth_assignments';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function booth(): BelongsTo
    {
        return $this->belongsTo(Booth::class);
    }

    public function eventExhibitor(): BelongsTo
    {
        return $this->belongsTo(EventExhibitor::class);
    }
}
