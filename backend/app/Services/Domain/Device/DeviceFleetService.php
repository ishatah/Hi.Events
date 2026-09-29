<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Device;

use Carbon\Carbon;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Heartbeats, queued commands and the fleet picture.
 *
 * Commands are collected by the device rather than pushed to it. The server never connects
 * into the venue LAN — venue networks are NATed and firewalled — so every instruction waits
 * to be picked up.
 *
 * @see docs/arzo-master-plan/40-device-management.md
 */
class DeviceFleetService
{
    private const OFFLINE_AFTER_SECONDS = 120;

    private const MAX_ACCEPTABLE_CLOCK_SKEW_SECONDS = 120;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * Records a heartbeat and returns anything the device should act on.
     *
     * @return array{clock_skew_seconds: int, skew_exceeded: bool, commands: array<int, object>}
     */
    public function heartbeat(
        int $deviceId,
        ?Carbon $deviceClock = null,
        ?int $batteryLevel = null,
        ?string $appVersion = null,
        ?string $syncCursor = null,
    ): array {
        $skew = $deviceClock !== null ? (int) round($deviceClock->diffInSeconds(now(), false)) : 0;

        $this->databaseManager->table('devices')
            ->where('id', $deviceId)
            ->update(array_filter([
                'last_seen_at' => now(),
                'battery_level' => $batteryLevel,
                'app_version' => $appVersion,
                'last_sync_cursor' => $syncCursor,
                'updated_at' => now(),
            ], static fn ($value): bool => $value !== null));

        $skewExceeded = abs($skew) > self::MAX_ACCEPTABLE_CLOCK_SKEW_SECONDS;

        // Only state changes are appended. A row per heartbeat would be volume with no
        // reader, and the current picture already lives on the device row.
        if ($skewExceeded || ($batteryLevel !== null && $batteryLevel <= 15)) {
            $this->recordHealthEvent(
                deviceId: $deviceId,
                state: $skewExceeded ? 'CLOCK_SKEW' : 'LOW_BATTERY',
                reason: $skewExceeded
                    ? __('Device clock differs from the server by :seconds seconds.', ['seconds' => $skew])
                    : __('Battery at :level percent.', ['level' => $batteryLevel]),
                batteryLevel: $batteryLevel,
                clockSkewSeconds: $skew,
            );
        }

        return [
            'clock_skew_seconds' => $skew,
            'skew_exceeded' => $skewExceeded,
            'commands' => $this->claimPendingCommands($deviceId),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function queueCommand(
        int $deviceId,
        string $command,
        ?array $payload = null,
        ?int $issuedByUserId = null,
    ): int {
        return (int) $this->databaseManager->table('device_commands')->insertGetId([
            'short_id' => 'dc_'.Str::lower(Str::random(20)),
            'device_id' => $deviceId,
            'command' => $command,
            'payload' => $payload !== null ? json_encode($payload) : null,
            'status' => 'PENDING',
            'issued_at' => now(),
            'issued_by' => $issuedByUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @throws ResourceConflictException
     */
    public function acknowledgeCommand(int $commandId, int $deviceId, ?string $result = null): void
    {
        $updated = $this->databaseManager->table('device_commands')
            ->where('id', $commandId)
            ->where('device_id', $deviceId)
            ->whereIn('status', ['PENDING', 'DELIVERED'])
            ->update([
                'status' => 'ACKNOWLEDGED',
                'acknowledged_at' => now(),
                'result' => $result,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw new ResourceConflictException(
                __('That command is not outstanding for this device.')
            );
        }
    }

    /**
     * The fleet picture: which devices are working, and which have gone quiet.
     *
     * Derived from last_seen_at rather than a stored online flag, because a device that
     * loses power never gets the chance to say it went offline.
     *
     * @return array<string, mixed>
     */
    public function fleetStatus(int $eventId): array
    {
        $devices = $this->databaseManager->table('devices')
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->get();

        $online = 0;
        $offline = 0;
        $pending = 0;
        $suspended = 0;
        $stale = [];

        foreach ($devices as $device) {
            if ((string) $device->status === 'PENDING') {
                $pending++;

                continue;
            }

            if ((string) $device->status === 'SUSPENDED') {
                $suspended++;

                continue;
            }

            $lastSeen = $device->last_seen_at !== null
                ? Carbon::parse((string) $device->last_seen_at)
                : null;

            if ($lastSeen !== null && $lastSeen->diffInSeconds(now()) <= self::OFFLINE_AFTER_SECONDS) {
                $online++;

                continue;
            }

            $offline++;
            $stale[] = [
                'device_id' => (int) $device->id,
                'name' => (string) $device->name,
                'device_type' => (string) $device->device_type,
                'last_seen_at' => $device->last_seen_at,
            ];
        }

        return [
            'total' => $devices->count(),
            'online' => $online,
            'offline' => $offline,
            'pending_pairing' => $pending,
            'suspended' => $suspended,
            'offline_devices' => $stale,
        ];
    }

    /**
     * Commands waiting for a device, marked as delivered on the way out.
     *
     * @return array<int, object>
     */
    private function claimPendingCommands(int $deviceId): array
    {
        $commands = $this->databaseManager->table('device_commands')
            ->where('device_id', $deviceId)
            ->where('status', 'PENDING')
            ->orderBy('issued_at')
            ->get();

        if ($commands->isEmpty()) {
            return [];
        }

        $this->databaseManager->table('device_commands')
            ->whereIn('id', $commands->pluck('id'))
            ->update(['status' => 'DELIVERED', 'delivered_at' => now(), 'updated_at' => now()]);

        return $commands->all();
    }

    private function recordHealthEvent(
        int $deviceId,
        string $state,
        ?string $reason,
        ?int $batteryLevel,
        ?int $clockSkewSeconds,
    ): void {
        // Repeating the same state on every heartbeat would flood the log, so a state is only
        // appended when it differs from the last one recorded.
        $lastState = $this->databaseManager->table('device_health_events')
            ->where('device_id', $deviceId)
            ->orderByDesc('occurred_at')
            ->value('state');

        if ((string) $lastState === $state) {
            return;
        }

        $this->databaseManager->table('device_health_events')->insert([
            'device_id' => $deviceId,
            'state' => $state,
            'reason' => $reason,
            'battery_level' => $batteryLevel,
            'clock_skew_seconds' => $clockSkewSeconds,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }
}
