<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\ApiKey;

use HiEvents\Services\Infrastructure\ApiKey\DTO\ApiPrincipalDTO;
use Illuminate\Database\DatabaseManager;

/**
 * Resolves a presented bearer key to the principal behind it.
 *
 * Every rejection returns null rather than distinguishing "no such key" from "wrong
 * secret" or "expired": telling a caller which of those it was confirms that a prefix
 * exists, which is a probe worth denying.
 *
 * @see docs/arzo-master-plan/48-api-platform.md
 */
class ApiKeyAuthenticator
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly ApiKeyHasher $hasher,
    ) {}

    public function resolve(?string $bearer, ?string $clientIp = null): ?ApiPrincipalDTO
    {
        if ($bearer === null || $bearer === '') {
            return null;
        }

        $parsed = $this->hasher->parse($bearer);

        if ($parsed === null) {
            return null;
        }

        return $this->hasher->isDeviceKey($parsed['prefix'])
            ? $this->resolveDevice($parsed['prefix'], $parsed['secret'])
            : $this->resolveApiKey($parsed['prefix'], $parsed['secret'], $clientIp);
    }

    private function resolveApiKey(string $prefix, string $secret, ?string $clientIp): ?ApiPrincipalDTO
    {
        $key = $this->databaseManager->table('api_keys')
            ->where('key_prefix', $prefix)
            ->whereNull('revoked_at')
            ->first();

        if ($key === null) {
            return null;
        }

        if (! $this->hasher->verify($secret, (string) $key->key_hash)) {
            return null;
        }

        if ($key->expires_at !== null && now()->gt($key->expires_at)) {
            return null;
        }

        if (! $this->ipIsAllowed($key->allowed_ips, $clientIp)) {
            return null;
        }

        $this->touch('api_keys', (int) $key->id);

        return new ApiPrincipalDTO(
            type: 'API_KEY',
            id: (int) $key->id,
            accountId: (int) $key->account_id,
            scopes: $this->decodeScopes($key->scopes),
            organizerId: $key->organizer_id !== null ? (int) $key->organizer_id : null,
            eventId: $key->event_id !== null ? (int) $key->event_id : null,
            rateLimitPerMinute: $key->rate_limit_per_minute !== null
                ? (int) $key->rate_limit_per_minute
                : null,
        );
    }

    private function resolveDevice(string $prefix, string $secret): ?ApiPrincipalDTO
    {
        $device = $this->databaseManager->table('devices')
            ->where('api_key_prefix', $prefix)
            ->whereNull('deleted_at')
            ->first();

        if ($device === null || $device->api_key_hash === null) {
            return null;
        }

        if (! $this->hasher->verify($secret, (string) $device->api_key_hash)) {
            return null;
        }

        // A device that has been suspended or retired keeps its row for the audit trail but
        // must stop working immediately — this is the revocation path for a lost tablet.
        if ($device->status !== 'ACTIVE') {
            return null;
        }

        $this->databaseManager->table('devices')
            ->where('id', $device->id)
            ->update(['last_seen_at' => now(), 'updated_at' => now()]);

        return new ApiPrincipalDTO(
            type: 'DEVICE',
            id: (int) $device->id,
            accountId: (int) $device->account_id,
            scopes: $this->deviceScopes($device),
            eventId: $device->event_id !== null ? (int) $device->event_id : null,
        );
    }

    /**
     * A device holds a fixed, minimal set rather than an editable list.
     *
     * This is the concrete answer to a lost tablet: whoever picks it up can submit scans
     * and sync, and can do nothing else with the key on it.
     *
     * @return array<int, string>
     */
    private function deviceScopes(object $device): array
    {
        $metadata = $device->metadata !== null
            ? (json_decode((string) $device->metadata, true) ?: [])
            : [];

        $granted = $metadata['scopes'] ?? null;

        if (is_array($granted) && $granted !== []) {
            return array_values(array_map('strval', $granted));
        }

        return match ((string) $device->device_type) {
            'PRINTER', 'PRINT_HOST' => ['badge.print'],
            'KIOSK' => ['attendee.checkin', 'device.submit_scan'],
            default => ['device.submit_scan'],
        };
    }

    /**
     * @param  mixed  $allowedIps
     */
    private function ipIsAllowed($allowedIps, ?string $clientIp): bool
    {
        if ($allowedIps === null) {
            return true;
        }

        $list = json_decode((string) $allowedIps, true);

        if (! is_array($list) || $list === []) {
            return true;
        }

        // An allowlist that cannot see the caller's address is not satisfied. Treating an
        // unknown IP as permitted would make the restriction disappear behind a proxy.
        if ($clientIp === null) {
            return false;
        }

        return in_array($clientIp, array_map('strval', $list), true);
    }

    /**
     * @return array<int, string>
     */
    private function decodeScopes(mixed $scopes): array
    {
        $decoded = json_decode((string) $scopes, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    private function touch(string $table, int $id): void
    {
        $this->databaseManager->table($table)
            ->where('id', $id)
            ->update(['last_used_at' => now()]);
    }
}
