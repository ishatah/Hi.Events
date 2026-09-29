<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Tenancy;

/**
 * The account the current unit of work belongs to.
 *
 * Registered as a scoped binding, so each request and each queued job resolves its own
 * instance. That is the point: the tenant id previously lived in a static property on the
 * User model, set by HTTP middleware and never cleared, so a queue worker or a long-lived
 * process could read the tenant of whichever request happened to run before it.
 *
 * An unset tenant is a normal state, not an error — console commands and the public
 * storefront legitimately have none. Callers decide what that means rather than being
 * handed an exception.
 *
 * @see docs/arzo-master-plan/08-multi-tenancy.md
 */
class TenantContext
{
    private ?int $accountId = null;

    private bool $scopeDisabled = false;

    public function setAccountId(?int $accountId): void
    {
        $this->accountId = $accountId;
    }

    public function accountId(): ?int
    {
        return $this->accountId;
    }

    public function hasAccount(): bool
    {
        return $this->accountId !== null;
    }

    public function clear(): void
    {
        $this->accountId = null;
    }

    /**
     * Runs a callback with tenant scoping suspended.
     *
     * The escape hatch is a narrow, explicit block rather than a flag someone can leave
     * set: platform admin endpoints and cross-tenant maintenance need it, and nothing else
     * should reach for it.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->scopeDisabled;
        $this->scopeDisabled = true;

        try {
            return $callback();
        } finally {
            $this->scopeDisabled = $previous;
        }
    }

    public function isScopeEnabled(): bool
    {
        return ! $this->scopeDisabled;
    }
}
