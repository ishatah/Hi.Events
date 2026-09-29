<?php

declare(strict_types=1);

namespace HiEvents\Models\Concerns;

use HiEvents\Models\Scopes\TenantScope;

/**
 * Applies the tenant scope to a model that carries its own account_id.
 *
 * @see docs/arzo-master-plan/08-multi-tenancy.md
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
