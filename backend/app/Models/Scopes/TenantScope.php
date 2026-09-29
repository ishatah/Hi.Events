<?php

declare(strict_types=1);

namespace HiEvents\Models\Scopes;

use HiEvents\Services\Infrastructure\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains a model to the current tenant's rows.
 *
 * This is the second line of defence, not the first. Actions still authorize explicitly;
 * this exists so that a forgotten authorization call degrades to an empty result instead of
 * another tenant's data. Child resources are scoped by their parent today
 * (findByEventId with no account_id), which means one omitted check is a full cross-tenant
 * read — that is the hole this closes.
 *
 * With no tenant set the scope does nothing, because console commands, queue workers and
 * the public storefront legitimately operate outside a tenant. Those paths are protected by
 * their own means, and silently returning nothing there would break them in ways that are
 * hard to trace.
 *
 * @see docs/arzo-master-plan/08-multi-tenancy.md
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if (! $context->isScopeEnabled() || ! $context->hasAccount()) {
            return;
        }

        $builder->where($model->qualifyColumn('account_id'), $context->accountId());
    }
}
