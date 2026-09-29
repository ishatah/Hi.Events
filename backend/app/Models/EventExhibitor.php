<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventExhibitor extends BaseModel
{
    use SoftDeletes;

    protected $table = 'event_exhibitors';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
