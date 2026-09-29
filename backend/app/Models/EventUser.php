<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventUser extends BaseModel
{
    protected $table = 'event_users';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permissionRole(): BelongsTo
    {
        return $this->belongsTo(PermissionRole::class);
    }
}
