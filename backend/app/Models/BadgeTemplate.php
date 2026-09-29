<?php

namespace HiEvents\Models;

use HiEvents\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BadgeTemplate extends BaseModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'badge_templates';

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
