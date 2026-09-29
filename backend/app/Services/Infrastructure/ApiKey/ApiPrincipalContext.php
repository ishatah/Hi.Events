<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\ApiKey;

use HiEvents\Services\Infrastructure\ApiKey\DTO\ApiPrincipalDTO;

/**
 * The machine principal for the current unit of work.
 *
 * Registered as a scoped binding for the same reason TenantContext is: a static would
 * survive into the next request or queued job and authorise it as whoever came before.
 *
 * @see docs/arzo-master-plan/48-api-platform.md
 */
class ApiPrincipalContext
{
    private ?ApiPrincipalDTO $principal = null;

    public function set(ApiPrincipalDTO $principal): void
    {
        $this->principal = $principal;
    }

    public function get(): ?ApiPrincipalDTO
    {
        return $this->principal;
    }

    public function has(): bool
    {
        return $this->principal !== null;
    }

    public function clear(): void
    {
        $this->principal = null;
    }
}
