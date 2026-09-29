<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadCapture extends BaseModel
{
    protected $table = 'lead_captures';

    protected function getTimestampsEnabled(): bool
    {
        return false;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventExhibitor(): BelongsTo
    {
        return $this->belongsTo(EventExhibitor::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
