<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExhibitorStaff extends BaseModel
{
    use SoftDeletes;

    protected $table = 'exhibitor_staff';

    public function eventExhibitor(): BelongsTo
    {
        return $this->belongsTo(EventExhibitor::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
