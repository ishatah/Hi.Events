<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAttendance extends BaseModel
{
    protected $table = 'session_attendance';

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}
