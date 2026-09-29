<?php

namespace HiEvents\Models;

use HiEvents\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'api_keys';

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
