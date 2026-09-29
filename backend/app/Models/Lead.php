<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends BaseModel
{
    use SoftDeletes;

    protected $table = 'leads';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventExhibitor(): BelongsTo
    {
        return $this->belongsTo(EventExhibitor::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
