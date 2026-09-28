<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends BaseModel
{
    protected $table = 'access_logs';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function access_point(): BelongsTo
    {
        return $this->belongsTo(AccessPoint::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
