<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SessionRegistration extends BaseModel
{
    use SoftDeletes;

    protected $table = 'session_registrations';

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}
