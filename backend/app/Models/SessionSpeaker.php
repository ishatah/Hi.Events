<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionSpeaker extends BaseModel
{
    protected $table = 'session_speakers';

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function speaker(): BelongsTo
    {
        return $this->belongsTo(Speaker::class);
    }
}
