<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccreditationType extends BaseModel
{
    use SoftDeletes;

    protected $table = 'accreditation_types';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function accreditation_type_rules(): HasMany
    {
        return $this->hasMany(AccreditationTypeRule::class);
    }
}
