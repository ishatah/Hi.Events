<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Device;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\AccessLogSource;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Access\AccessScanService;
use HiEvents\Services\Domain\Device\DTO\SyncResultDTO;
use Illuminate\Database\DatabaseManager;

/**
 * One call in each direction: the device pushes what it recorded offline and pulls what
 * changed since its cursor.
 *
 * Bidirectional in a single request because a bad link makes round-trips expensive, and a
 * scanner at a gate cannot wait. Everything here is shaped by at-least-once delivery:
 * replaying a queued scan must be a no-op, and a partial failure must leave the device able
 * to try again without losing anything.
 *
 * @see docs/arzo-master-plan/71-realtime-architecture.md
 */
class DeviceSyncService
{
    /**
     * Bounded so a device that has been offline for a day does not receive a response it
     * cannot parse in memory. It simply syncs again with the new cursor.
     */
    private const MAX_DELTA_ROWS = 500;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly AccessScanService $accessScanService,
        private readonly AccessReconciliationService $reconciliation,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $pendingLogs
     *
     * @throws ResourceConflictException
     */
    public function sync(int $deviceId, ?string $cursor, array $pendingLogs = []): SyncResultDTO
    {
        $device = $this->databaseManager->table('devices')
            ->where('id', $deviceId)
            ->whereNull('deleted_at')
            ->first();

        if ($device === null) {
            throw new ResourceConflictException(__('That device could not be found.'));
        }

        if ((string) $device->status !== 'ACTIVE') {
            throw new ResourceConflictException(__('This device is not active.'));
        }

        if ($device->event_id === null) {
            throw new ResourceConflictException(__('This device is not assigned to an event.'));
        }

        $eventId = (int) $device->event_id;

        $accepted = $this->ingestPendingLogs($eventId, $deviceId, $pendingLogs);

        // Deny-list first. Revocation propagation matters more than a new registration: a
        // device that learns about one extra attendee late is inconvenient, one that keeps
        // admitting a revoked badge is a security failure.
        $denyList = $this->denyList($eventId);

        $since = $this->cursorToTimestamp($cursor);
        $credentials = $this->credentialDelta($eventId, $since);
        $grants = $this->grantDelta($eventId, $since);

        $newCursor = $this->advanceCursor($credentials, $grants, $since);

        $this->databaseManager->table('devices')
            ->where('id', $deviceId)
            ->update([
                'last_sync_cursor' => $newCursor,
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);

        return new SyncResultDTO(
            acceptedLogs: $accepted['accepted'],
            duplicateLogs: $accepted['duplicates'],
            rejectedLogs: $accepted['rejected'],
            credentialsDelta: $credentials,
            grantsDelta: $grants,
            denyList: $denyList,
            config: $this->config($eventId, $device),
            newCursor: $newCursor,
            // Tells the device there is more waiting so it syncs again immediately rather
            // than after its normal interval.
            hasMore: count($credentials) >= self::MAX_DELTA_ROWS || count($grants) >= self::MAX_DELTA_ROWS,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $pendingLogs
     * @return array{accepted: int, duplicates: int, rejected: array<int, array<string, mixed>>}
     */
    private function ingestPendingLogs(int $eventId, int $deviceId, array $pendingLogs): array
    {
        $accepted = 0;
        $duplicates = 0;
        $rejected = [];

        foreach ($pendingLogs as $log) {
            $clientId = $log['client_generated_id'] ?? null;

            if ($clientId === null) {
                // Without an idempotency key a replay would double-admit. Rejecting is safer
                // than accepting something that cannot be deduplicated.
                $rejected[] = ['log' => $log, 'reason' => 'missing_client_generated_id'];

                continue;
            }

            $alreadyRecorded = $this->databaseManager->table('access_logs')
                ->where('event_id', $eventId)
                ->where('client_generated_id', $clientId)
                ->exists();

            if ($alreadyRecorded) {
                $duplicates++;

                continue;
            }

            try {
                $decision = $this->accessScanService->scan(
                    eventId: $eventId,
                    identifier: (string) ($log['identifier'] ?? ''),
                    accessPointId: (int) ($log['access_point_id'] ?? 0),
                    direction: null,
                    deviceId: $deviceId,
                    clientGeneratedId: (string) $clientId,
                    occurredAt: isset($log['occurred_at'])
                        ? Carbon::parse((string) $log['occurred_at'])
                        : null,
                    identifierType: (string) ($log['identifier_type'] ?? 'QR'),
                    source: AccessLogSource::OFFLINE_SYNC->value,
                );

                $accepted++;

                // The device made its own call offline. Where the server now disagrees, the
                // disagreement is recorded beside the log rather than by editing it.
                $this->reconciliation->reviewSubmittedLog(
                    eventId: $eventId,
                    deviceId: $deviceId,
                    clientGeneratedId: (string) $clientId,
                    deviceDecision: $log['result'] ?? null,
                    serverDecision: $decision->result->value,
                );
            } catch (\Throwable $exception) {
                // A single unparseable row must not cost the device the rest of its queue.
                $rejected[] = ['log' => $log, 'reason' => $exception->getMessage()];
            }
        }

        return ['accepted' => $accepted, 'duplicates' => $duplicates, 'rejected' => $rejected];
    }

    /**
     * Credentials that are no longer valid, so a disconnected door stops admitting them.
     *
     * Sent in full rather than as a delta: it is small, and a device that missed one delta
     * would otherwise keep admitting a revoked badge until it caught up.
     *
     * @return array<int, string>
     */
    private function denyList(int $eventId): array
    {
        return $this->databaseManager->table('credentials')
            ->where('event_id', $eventId)
            ->whereIn('status', ['REVOKED', 'SUSPENDED', 'EXPIRED'])
            ->pluck('identifier_hash')
            ->map(static fn ($hash): string => (string) $hash)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function credentialDelta(int $eventId, ?Carbon $since): array
    {
        $query = $this->databaseManager->table('credentials')
            ->where('event_id', $eventId)
            ->orderBy('updated_at')
            ->limit(self::MAX_DELTA_ROWS)
            ->select(['id', 'identifier_hash', 'status', 'credential_type', 'valid_from', 'valid_until', 'updated_at']);

        if ($since !== null) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(static fn (object $row): array => (array) $row)->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function grantDelta(int $eventId, ?Carbon $since): array
    {
        $query = $this->databaseManager->table('access_grants')
            ->join('credentials', 'credentials.id', '=', 'access_grants.credential_id')
            ->where('credentials.event_id', $eventId)
            ->orderBy('access_grants.updated_at')
            ->limit(self::MAX_DELTA_ROWS)
            ->select([
                'access_grants.id',
                'access_grants.credential_id',
                'access_grants.zone_id',
                'access_grants.room_id',
                'access_grants.session_id',
                'access_grants.starts_at',
                'access_grants.ends_at',
                'access_grants.days_of_week',
                'access_grants.time_from',
                'access_grants.time_to',
                'access_grants.max_entries',
                'access_grants.allow_reentry',
                'access_grants.min_reentry_seconds',
                'access_grants.status',
                'access_grants.updated_at',
            ]);

        if ($since !== null) {
            $query->where('access_grants.updated_at', '>', $since);
        }

        return $query->get()->map(static fn (object $row): array => (array) $row)->all();
    }

    /**
     * Everything a device needs to decide offline.
     *
     * @return array<string, mixed>
     */
    private function config(int $eventId, object $device): array
    {
        $zones = $this->databaseManager->table('zones')
            ->join('event_venues', 'event_venues.venue_id', '=', 'zones.venue_id')
            ->where('event_venues.event_id', $eventId)
            ->whereNull('zones.deleted_at')
            ->select(['zones.id', 'zones.code', 'zones.name', 'zones.capacity', 'zones.colour'])
            ->get()
            ->all();

        $accessPoints = $this->databaseManager->table('access_points')
            ->join('zones', 'zones.id', '=', 'access_points.zone_id')
            ->join('event_venues', 'event_venues.venue_id', '=', 'zones.venue_id')
            ->where('event_venues.event_id', $eventId)
            ->where('access_points.is_active', true)
            ->whereNull('access_points.deleted_at')
            ->select(['access_points.id', 'access_points.code', 'access_points.direction', 'access_points.zone_id'])
            ->get()
            ->all();

        $rules = $this->databaseManager->table('access_rules')
            ->where('event_id', $eventId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('priority')
            ->get()
            ->all();

        $timezone = $this->databaseManager->table('events')
            ->where('id', $eventId)
            ->value('timezone');

        return [
            'event_id' => $eventId,
            'event_timezone' => $timezone ?: 'UTC',
            'device_id' => (int) $device->id,
            'device_type' => (string) $device->device_type,
            'access_point_id' => $device->access_point_id !== null ? (int) $device->access_point_id : null,
            'zones' => $zones,
            'access_points' => $accessPoints,
            'rules' => $rules,
        ];
    }

    private function cursorToTimestamp(?string $cursor): ?Carbon
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        try {
            return Carbon::parse($cursor);
        } catch (\Throwable) {
            // An unparseable cursor means a full refresh rather than an error. A device with
            // corrupted local state should be able to recover by syncing.
            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $credentials
     * @param  array<int, array<string, mixed>>  $grants
     */
    private function advanceCursor(array $credentials, array $grants, ?Carbon $since): string
    {
        $timestamps = [];

        foreach ([$credentials, $grants] as $rows) {
            foreach ($rows as $row) {
                if (isset($row['updated_at'])) {
                    $timestamps[] = Carbon::parse((string) $row['updated_at']);
                }
            }
        }

        if ($timestamps === []) {
            // Nothing changed, so the cursor holds. Advancing it to now() would skip a row
            // written in the same second the sync ran.
            return ($since ?? Carbon::createFromTimestamp(0))->toIso8601String();
        }

        usort($timestamps, static fn (Carbon $a, Carbon $b): int => $a <=> $b);

        return end($timestamps)->toIso8601String();
    }
}
