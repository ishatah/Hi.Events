<?php

namespace HiEvents\Models;

use HiEvents\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxAndFee extends BaseModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'taxes_and_fees';

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_taxes_and_fees');
    }

    protected function getCastMap(): array
    {
        return [
            'rate' => 'float',
        ];
    }
}
