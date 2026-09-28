<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccreditationTypeRule extends BaseModel
{
    protected $table = 'accreditation_type_rules';

    public function accreditation_type(): BelongsTo
    {
        return $this->belongsTo(AccreditationType::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
