<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgePrintJob extends BaseModel
{
    protected $table = 'badge_print_jobs';

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}
