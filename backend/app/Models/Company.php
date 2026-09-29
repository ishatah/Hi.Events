<?php

namespace HiEvents\Models;

use HiEvents\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends BaseModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'companies';

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
