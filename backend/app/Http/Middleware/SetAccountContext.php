<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Models\User;
use HiEvents\Services\Infrastructure\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;

class SetAccountContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle($request, Closure $next)
    {
        if (Auth::check()) {
            $accountId = Auth::payload()->get('account_id');

            if ($accountId) {
                $this->tenantContext->setAccountId((int) $accountId);
                User::setCurrentAccountId($accountId);
            }
        }

        return $next($request);
    }
}
