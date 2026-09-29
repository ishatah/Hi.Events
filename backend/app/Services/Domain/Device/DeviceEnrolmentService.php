<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Device;

use Carbon\Carbon;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Infrastructure\ApiKey\ApiKeyHasher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Enrols devices and issues their keys.
 *
 * Pairing is a short-lived code typed on the device rather than a key pasted into it: a key
 * emailed or read aloud ends up in a chat log, and a code that expires in minutes limits
 * what a shoulder-surfer gets.
 *
 * @see docs/arzo-master-plan/40-device-management.md
 */
class DeviceEnrolmentService
{
    private const PAIRING_CODE_TTL_MINUTES = 15;

    private const PAIRING_CODE_LENGTH = 8;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly ApiKeyHasher $hasher,
    ) {}

    /**
     * Registers a device and returns the code to type into it.
     *
     * @return array{device_id: int, pairing_code: string, expires_at: Carbon}
     */
    public function register(
        int $accountId,
        string $name,
        string $deviceType,
        ?int $eventId = null,
        ?int $accessPointId = null,
    ): array {
        // Unambiguous alphabet: no O/0 or I/1, because this is read off a screen and typed
        // on a tablet by somebody standing at a gate.
        $code = Str::upper(Str::password(self::PAIRING_CODE_LENGTH, letters: true, numbers: true, symbols: false));
        $code = str_replace(['O', '0', 'I', '1', 'L'], ['X', 'Y', 'Z', 'W', 'V'], $code);

        $expiresAt = now()->addMinutes(self::PAIRING_CODE_TTL_MINUTES);

        $deviceId = (int) $this->databaseManager->table('devices')->insertGetId([
            'short_id' => 'dv_'.Str::lower(Str::random(20)),
            'account_id' => $accountId,
            'event_id' => $eventId,
            'access_point_id' => $accessPointId,
            'name' => $name,
            'device_type' => $deviceType,
            'status' => 'PENDING',
            'pairing_code' => $code,
            'pairing_code_expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['device_id' => $deviceId, 'pairing_code' => $code, 'expires_at' => $expiresAt];
    }

    /**
     * Exchanges a pairing code for the device's key.
     *
     * The key is returned once. The code is consumed whether or not the device stores the key
     * successfully, because a code that survives a failed attempt can be replayed.
     *
     * @return array{device_id: int, key: string}
     *
     * @throws ResourceConflictException
     */
    public function pair(string $pairingCode): array
    {
        return $this->databaseManager->transaction(function () use ($pairingCode): array {
            $device = $this->databaseManager->table('devices')
                ->where('pairing_code', Str::upper($pairingCode))
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($device === null) {
                throw new ResourceConflictException(__('That pairing code is not valid.'));
            }

            if ($device->pairing_code_expires_at === null
                || now()->gt(Carbon::parse((string) $device->pairing_code_expires_at))
            ) {
                throw new ResourceConflictException(__('That pairing code has expired.'));
            }

            $key = $this->hasher->generate(ApiKeyHasher::DEVICE_KEY_PREFIX);

            $this->databaseManager->table('devices')
                ->where('id', $device->id)
                ->update([
                    'status' => 'ACTIVE',
                    'api_key_prefix' => $key['prefix'],
                    'api_key_hash' => $key['hash'],
                    'pairing_code' => null,
                    'pairing_code_expires_at' => null,
                    'last_seen_at' => now(),
                    'updated_at' => now(),
                ]);

            return ['device_id' => (int) $device->id, 'key' => $key['plaintext']];
        });
    }

    /**
     * Suspends a device, which is how a lost tablet is revoked.
     *
     * Its key stops authenticating on the next request. The row is kept so the audit trail
     * still says which device recorded which scans.
     *
     * @throws ResourceConflictException
     */
    public function suspend(int $deviceId, int $accountId): void
    {
        $updated = $this->databaseManager->table('devices')
            ->where('id', $deviceId)
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->update(['status' => 'SUSPENDED', 'updated_at' => now()]);

        if ($updated === 0) {
            throw new ResourceConflictException(__('That device could not be found.'));
        }
    }

    /**
     * Issues a fresh key, invalidating the old one.
     *
     * @return array{key: string}
     *
     * @throws ResourceConflictException
     */
    public function rotateKey(int $deviceId, int $accountId): array
    {
        $device = $this->databaseManager->table('devices')
            ->where('id', $deviceId)
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->first();

        if ($device === null) {
            throw new ResourceConflictException(__('That device could not be found.'));
        }

        $key = $this->hasher->generate(ApiKeyHasher::DEVICE_KEY_PREFIX);

        $this->databaseManager->table('devices')
            ->where('id', $deviceId)
            ->update([
                'api_key_prefix' => $key['prefix'],
                'api_key_hash' => $key['hash'],
                'updated_at' => now(),
            ]);

        return ['key' => $key['plaintext']];
    }
}
