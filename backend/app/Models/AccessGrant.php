<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessGrant extends BaseModel
{
    protected $table = 'access_grants';

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function access_point(): BelongsTo
    {
        return $this->belongsTo(AccessPoint::class);
    }
}
