<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\ApiKey;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Permission\PermissionResolutionService;
use HiEvents\Services\Infrastructure\ApiKey\ApiKeyHasher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Issues and revokes API keys.
 *
 * @see docs/arzo-master-plan/48-api-platform.md
 */
class ApiKeyIssuanceService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly ApiKeyHasher $hasher,
        private readonly PermissionResolutionService $permissionResolutionService,
    ) {}

    /**
     * @param  array<int, string>  $scopes
     * @return array{id: int, plaintext: string, prefix: string}
     *
     * @throws ResourceConflictException
     */
    public function issue(
        int $accountId,
        int $creatorUserId,
        string $name,
        array $scopes,
        ?int $organizerId = null,
        ?int $eventId = null,
        ?int $rateLimitPerMinute = null,
        ?array $allowedIps = null,
        ?string $expiresAt = null,
    ): array {
        if ($scopes === []) {
            throw new ResourceConflictException(__('A key must carry at least one scope.'));
        }

        $this->guardScopesAreReal($scopes);
        $this->guardCreatorHoldsScopes($creatorUserId, $accountId, $eventId, $scopes);

        if ($eventId !== null) {
            $this->guardEventBelongsToAccount($eventId, $accountId);
        }

        $key = $this->hasher->generate(ApiKeyHasher::API_KEY_PREFIX);

        $id = (int) $this->databaseManager->table('api_keys')->insertGetId([
            'short_id' => 'ak_'.Str::lower(Str::random(20)),
            'account_id' => $accountId,
            'organizer_id' => $organizerId,
            'event_id' => $eventId,
            'name' => $name,
            'key_prefix' => $key['prefix'],
            'key_hash' => $key['hash'],
            'scopes' => json_encode(array_values(array_unique($scopes))),
            'rate_limit_per_minute' => $rateLimitPerMinute,
            'allowed_ips' => $allowedIps !== null ? json_encode(array_values($allowedIps)) : null,
            'expires_at' => $expiresAt,
            'created_by' => $creatorUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The plaintext is returned once and never stored. Nothing can recover it later,
        // which is the point, and the UI has to say so because users assume otherwise.
        return [
            'id' => $id,
            'plaintext' => $key['plaintext'],
            'prefix' => $key['prefix'],
        ];
    }

    /**
     * @throws ResourceConflictException
     */
    public function revoke(int $apiKeyId, int $accountId, ?int $revokedByUserId): void
    {
        $key = $this->databaseManager->table('api_keys')
            ->where('id', $apiKeyId)
            ->where('account_id', $accountId)
            ->first();

        if ($key === null) {
            throw new ResourceConflictException(__('The API key could not be found.'));
        }

        if ($key->revoked_at !== null) {
            throw new ResourceConflictException(__('This API key is already revoked.'));
        }

        $this->databaseManager->table('api_keys')
            ->where('id', $apiKeyId)
            ->update([
                'revoked_at' => now(),
                'revoked_by' => $revokedByUserId,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<int, string>  $scopes
     *
     * @throws ResourceConflictException
     */
    private function guardScopesAreReal(array $scopes): void
    {
        $known = array_map(
            static fn (Permission $permission): string => $permission->value,
            Permission::cases()
        );

        $unknown = array_diff($scopes, $known);

        if ($unknown !== []) {
            throw new ResourceConflictException(
                __('Unknown scope: :scope', ['scope' => (string) reset($unknown)])
            );
        }
    }

    /**
     * A key cannot carry a scope its creator does not hold.
     *
     * Without this, key creation is privilege escalation: a check-in operator could mint a
     * key that refunds orders and then use it.
     *
     * @param  array<int, string>  $scopes
     *
     * @throws ResourceConflictException
     */
    private function guardCreatorHoldsScopes(
        int $creatorUserId,
        int $accountId,
        ?int $eventId,
        array $scopes,
    ): void {
        $held = $eventId !== null
            ? $this->permissionResolutionService->eventPermissions($creatorUserId, $accountId, $eventId)
            : $this->permissionResolutionService->accountPermissions($creatorUserId, $accountId);

        $exceeded = array_diff($scopes, $held);

        if ($exceeded !== []) {
            throw new ResourceConflictException(
                __('You cannot grant a scope you do not hold: :scope', [
                    'scope' => (string) reset($exceeded),
                ])
            );
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function guardEventBelongsToAccount(int $eventId, int $accountId): void
    {
        $belongs = $this->databaseManager->table('events')
            ->where('id', $eventId)
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $belongs) {
            throw new ResourceConflictException(__('That event does not belong to this account.'));
        }
    }
}
