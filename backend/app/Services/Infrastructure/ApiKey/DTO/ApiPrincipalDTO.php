<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\ApiKey\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\Permission;

/**
 * The machine principal behind a request.
 *
 * An API key and a device key are different rows in different tables but the same kind of
 * actor: something holding an explicit, bounded list of scopes. Collapsing them here means
 * authorization has one shape to check rather than two.
 *
 * @see docs/arzo-master-plan/48-api-platform.md
 */
class ApiPrincipalDTO extends BaseDataObject
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly int $accountId,
        public readonly array $scopes,
        public readonly ?int $organizerId = null,
        public readonly ?int $eventId = null,
        public readonly ?int $rateLimitPerMinute = null,
    ) {}

    public function isDevice(): bool
    {
        return $this->type === 'DEVICE';
    }

    public function hasScope(Permission $permission): bool
    {
        return in_array($permission->value, $this->scopes, true);
    }

    /**
     * A key scoped to one event must not reach another.
     *
     * An unscoped key is account-wide by design, which is why this returns true when
     * eventId is null rather than denying.
     */
    public function coversEvent(int $eventId): bool
    {
        return $this->eventId === null || $this->eventId === $eventId;
    }
}
