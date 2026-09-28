<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionProduct extends BaseModel
{
    protected $table = 'session_products';

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
