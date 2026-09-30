<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\CommandCentre;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\AccessResult;
use HiEvents\Services\Domain\CommandCentre\DTO\OperationalAlertDTO;
use HiEvents\Services\Domain\Queue\DTO\QueueEstimateDTO;
use HiEvents\Services\Domain\Queue\QueueEstimationService;
use HiEvents\Services\Domain\Staffing\ShiftAssignmentService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * The event-day dashboard, assembled from primary records.
 *
 * Counters derive from the access log rather than from stored totals, because a stored counter
 * drifts the moment an offline device replays its queue and the number nobody can reconcile is
 * the one that gets argued about at 6pm.
 *
 * The screen reports its own staleness. If a gate's device has not synced for twenty minutes
 * then that gate's figures are twenty minutes old, and a dashboard that looks live while
 * showing stale data is worse than one that admits it: the operator has to know what they do
 * not know.
 *
 * @see docs/arzo-master-plan/53-live-event-command-center.md
 */
class CommandCentreService
{
    private const ARRIVAL_WINDOW_SECONDS = 300;

    private const STALE_DEVICE_MINUTES = 20;

    private const QUEUE_ALERT_DEPTH = 25;

    private const DENIAL_RATE_ALERT = 0.25;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly QueueEstimationService $queueEstimationService,
        private readonly ShiftAssignmentService $shiftAssignmentService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $eventId, ?CarbonImmutable $now = null): array
    {
        $at = $now ?? CarbonImmutable::now();

        $staleness = $this->staleness($eventId, $at);
        $admission = $this->admission($eventId, $at);
        $queues = $this->queueEstimationService->estimateForEvent($eventId, self::ARRIVAL_WINDOW_SECONDS, $at);
        $occupancy = $this->occupancy($eventId);
        $fleet = $this->fleet($eventId, $at);
        $incidents = $this->incidents($eventId);
        $staffing = $this->staffing($eventId);

        return [
            'generated_at' => $at->toIso8601String(),
            'staleness' => $staleness,
            'admission' => $admission,
            'queues' => $queues->map(
                static fn (QueueEstimateDTO $estimate): array => $estimate->toArray()
                    + ['suggests_misconfiguration' => $estimate->suggestsMisconfiguration()]
            )->all(),
            'occupancy' => $occupancy,
            'fleet' => $fleet,
            'incidents' => $incidents,
            'staffing' => $staffing,
            'alerts' => $this->alerts($queues, $occupancy, $fleet, $admission, $staffing)
                ->map(static fn (OperationalAlertDTO $alert): array => $alert->toArray())
                ->all(),
        ];
    }

    /**
     * How far behind the screen might be.
     *
     * A device that has not checked in recently is still holding scans, so every figure that
     * would include its gate is provisional until it syncs. Reporting this is the difference
     * between a dashboard an operator can act on and one that quietly misleads them.
     *
     * @return array<string, mixed>
     */
    public function staleness(int $eventId, ?CarbonImmutable $now = null): array
    {
        $at = $now ?? CarbonImmutable::now();
        $threshold = $at->subMinutes(self::STALE_DEVICE_MINUTES);

        $devices = $this->databaseManager->table('devices')
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'RETIRED')
            ->get(['id', 'name', 'last_seen_at']);

        $stale = $devices->filter(static function (object $device) use ($threshold): bool {
            return $device->last_seen_at === null
                || CarbonImmutable::parse((string) $device->last_seen_at)->lessThan($threshold);
        })->values();

        $oldestSync = $devices
            ->map(static fn (object $device): ?string => $device->last_seen_at)
            ->filter()
            ->map(static fn (string $timestamp): CarbonImmutable => CarbonImmutable::parse($timestamp))
            ->min();

        return [
            'is_provisional' => $stale->isNotEmpty(),
            'devices_total' => $devices->count(),
            'devices_stale' => $stale->count(),
            'stale_device_names' => $stale->pluck('name')->all(),
            'oldest_sync_at' => $oldestSync?->toIso8601String(),
            'oldest_sync_minutes_ago' => $oldestSync !== null
                ? (int) $oldestSync->diffInMinutes($at)
                : null,
            'threshold_minutes' => self::STALE_DEVICE_MINUTES,
        ];
    }

    /**
     * Are people getting in.
     *
     * Denials are broken out by reason rather than shown as one line, because a spike in
     * DENIED_NO_GRANT is a misconfigured rule and a spike in DENIED_CAPACITY is a full room:
     * the two need opposite responses, and a single "denied" figure hides which it is.
     *
     * @return array<string, mixed>
     */
    public function admission(int $eventId, ?CarbonImmutable $now = null): array
    {
        $at = $now ?? CarbonImmutable::now();

        $expected = (int) $this->databaseManager->table('attendees')
            ->where('event_id', $eventId)
            ->where('status', 'ACTIVE')
            ->whereNull('deleted_at')
            ->count();

        $arrivedPeople = (int) $this->databaseManager->table('access_logs')
            ->where('event_id', $eventId)
            ->where('direction', 'ENTRY')
            ->whereIn('result', [AccessResult::GRANTED->value, AccessResult::GRANTED_OVERRIDE->value])
            ->whereNotNull('person_id')
            ->distinct()
            ->count('person_id');

        $byResult = $this->databaseManager->table('access_logs')
            ->where('event_id', $eventId)
            ->where('direction', 'ENTRY')
            ->where('occurred_at', '>=', $at->subSeconds(self::ARRIVAL_WINDOW_SECONDS))
            ->selectRaw('result, count(*) as total')
            ->groupBy('result')
            ->pluck('total', 'result')
            ->map(static fn ($total): int => (int) $total);

        $granted = 0;
        $denials = [];

        foreach ($byResult as $result => $total) {
            if (AccessResult::tryFrom((string) $result)?->isGranted() === true) {
                $granted += $total;

                continue;
            }

            $denials[(string) $result] = $total;
        }

        $windowTotal = $granted + array_sum($denials);
        $minutes = self::ARRIVAL_WINDOW_SECONDS / 60;

        return [
            'expected' => $expected,
            'arrived' => $arrivedPeople,
            'not_arrived' => max(0, $expected - $arrivedPeople),
            // Only meaningful against a known expected figure: an event selling on the door
            // has no denominator, and a percentage of nothing reads as nobody arriving.
            'arrived_share' => $expected > 0 ? round($arrivedPeople / $expected, 3) : null,
            'arrivals_per_minute' => round($windowTotal / $minutes, 2),
            'granted_per_minute' => round($granted / $minutes, 2),
            'denials_by_reason' => $denials,
            'denial_rate' => $windowTotal > 0 ? round(array_sum($denials) / $windowTotal, 3) : 0.0,
            'window_seconds' => self::ARRIVAL_WINDOW_SECONDS,
        ];
    }

    /**
     * Where people are.
     *
     * Reads the occupancy snapshot cache, with the live aggregate as the authoritative
     * fallback: a zone with no snapshot yet should read as unknown rather than empty.
     *
     * @return array<int, array<string, mixed>>
     */
    public function occupancy(int $eventId): array
    {
        $zones = $this->databaseManager->table('zones')
            ->join('event_venues', 'event_venues.venue_id', '=', 'zones.venue_id')
            ->where('event_venues.event_id', $eventId)
            ->whereNull('zones.deleted_at')
            ->select(['zones.id', 'zones.name', 'zones.capacity'])
            ->get();

        if ($zones->isEmpty()) {
            return [];
        }

        $zoneIds = $zones->pluck('id');

        // One row per zone from the database rather than every snapshot ever taken: the
        // occupancy job writes every thirty seconds, so a multi-day event would otherwise load
        // tens of thousands of rows to keep a handful.
        //
        // Keyed on the latest captured_at rather than the highest id, because a backfill or a
        // replayed sync can insert an older measurement afterwards, and the dashboard must
        // show the most recent reading rather than the most recently written row.
        $latest = $this->databaseManager->table('zone_occupancy_snapshots as s')
            ->whereIn('s.zone_id', $zoneIds)
            ->whereRaw(
                's.captured_at = (
                    select max(latest.captured_at)
                    from zone_occupancy_snapshots latest
                    where latest.zone_id = s.zone_id
                )'
            )
            ->get(['s.zone_id', 's.occupancy', 's.captured_at'])
            ->unique('zone_id')
            ->keyBy('zone_id');

        return $zones->map(static function (object $zone) use ($latest): array {
            $snapshot = $latest->get($zone->id);
            $capacity = $zone->capacity !== null ? (int) $zone->capacity : null;
            $occupancy = $snapshot !== null ? (int) $snapshot->occupancy : null;

            return [
                'zone_id' => (int) $zone->id,
                'name' => (string) $zone->name,
                'capacity' => $capacity,
                'occupancy' => $occupancy,
                'utilisation' => $occupancy !== null && $capacity !== null && $capacity > 0
                    ? round($occupancy / $capacity, 3)
                    : null,
                'at_capacity' => $occupancy !== null && $capacity !== null && $capacity > 0
                    && $occupancy >= $capacity,
                'measured_at' => $snapshot?->captured_at,
            ];
        })->all();
    }

    /**
     * Is the equipment working.
     *
     * @return array<string, mixed>
     */
    public function fleet(int $eventId, ?CarbonImmutable $now = null): array
    {
        $at = $now ?? CarbonImmutable::now();
        $threshold = $at->subMinutes(self::STALE_DEVICE_MINUTES);

        $devices = $this->databaseManager->table('devices')
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'RETIRED')
            ->get(['id', 'name', 'device_type', 'status', 'last_seen_at', 'battery_level', 'app_version']);

        $offline = $devices->filter(static function (object $device) use ($threshold): bool {
            return $device->last_seen_at === null
                || CarbonImmutable::parse((string) $device->last_seen_at)->lessThan($threshold);
        })->values();

        $lowBattery = $devices->filter(
            static fn (object $device): bool => $device->battery_level !== null
                && (int) $device->battery_level <= 20
        )->values();

        return [
            'total' => $devices->count(),
            'online' => $devices->count() - $offline->count(),
            'offline' => $offline->count(),
            'offline_devices' => $offline->map(static fn (object $device): array => [
                'device_id' => (int) $device->id,
                'name' => (string) $device->name,
                'last_seen_at' => $device->last_seen_at,
            ])->all(),
            'low_battery' => $lowBattery->map(static fn (object $device): array => [
                'device_id' => (int) $device->id,
                'name' => (string) $device->name,
                'battery_level' => (int) $device->battery_level,
            ])->all(),
            'app_versions' => $devices->pluck('app_version')->filter()->countBy()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function incidents(int $eventId): array
    {
        $bySeverity = $this->databaseManager->table('incidents')
            ->where('event_id', $eventId)
            ->whereNotIn('status', ['RESOLVED', 'CLOSED'])
            ->whereNull('deleted_at')
            ->selectRaw('severity, count(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity')
            ->map(static fn ($total): int => (int) $total);

        $overrides = (int) $this->databaseManager->table('access_logs')
            ->where('event_id', $eventId)
            ->where('result', AccessResult::GRANTED_OVERRIDE->value)
            ->count();

        return [
            'open_by_severity' => $bySeverity->all(),
            'open_total' => (int) $bySeverity->sum(),
            'access_overrides' => $overrides,
        ];
    }

    /**
     * Who is working.
     *
     * @return array<string, mixed>
     */
    public function staffing(int $eventId): array
    {
        $gaps = $this->shiftAssignmentService->understaffedShifts($eventId);

        return [
            'understaffed_shifts' => count($gaps),
            'positions_short_by' => array_sum(array_map(
                static fn (array $gap): int => $gap['required'] - $gap['filled'],
                $gaps
            )),
            'gaps' => $gaps,
        ];
    }

    /**
     * Alerts on what attendees feel, not on infrastructure noise.
     *
     * @param  Collection<int, QueueEstimateDTO>  $queues
     * @param  array<int, array<string, mixed>>  $occupancy
     * @param  array<string, mixed>  $fleet
     * @param  array<string, mixed>  $admission
     * @param  array<string, mixed>  $staffing
     * @return Collection<int, OperationalAlertDTO>
     */
    private function alerts(
        Collection $queues,
        array $occupancy,
        array $fleet,
        array $admission,
        array $staffing,
    ): Collection {
        $alerts = collect();

        foreach ($queues as $queue) {
            if ($queue->estimatedQueueDepth >= self::QUEUE_ALERT_DEPTH) {
                $alerts->push(new OperationalAlertDTO(
                    code: 'QUEUE_BUILDING',
                    severity: 'HIGH',
                    subject: 'access_point:'.$queue->accessPointId,
                    detail: __('Around :count people waiting, state :state.', [
                        'count' => $queue->estimatedQueueDepth,
                        'state' => $queue->state->value,
                    ]),
                ));
            }

            // A door denying most scans needs its rule fixed, not more staff, so it is a
            // separate alert rather than another queue warning.
            if ($queue->suggestsMisconfiguration()) {
                $alerts->push(new OperationalAlertDTO(
                    code: 'DENIALS_CLUSTERED',
                    severity: 'MEDIUM',
                    subject: 'access_point:'.$queue->accessPointId,
                    detail: __(':share of scans denied at one door, which usually means a rule rather than a crowd.', [
                        'share' => round($queue->deniedShare * 100).'%',
                    ]),
                ));
            }
        }

        foreach ($occupancy as $zone) {
            if ($zone['at_capacity'] === true) {
                $alerts->push(new OperationalAlertDTO(
                    code: 'ZONE_AT_CAPACITY',
                    severity: 'HIGH',
                    subject: 'zone:'.$zone['zone_id'],
                    detail: __(':name is at :occupancy of :capacity.', [
                        'name' => $zone['name'],
                        'occupancy' => $zone['occupancy'],
                        'capacity' => $zone['capacity'],
                    ]),
                ));
            }
        }

        if ($fleet['offline'] > 0) {
            $alerts->push(new OperationalAlertDTO(
                code: 'DEVICES_OFFLINE',
                severity: 'MEDIUM',
                subject: 'fleet',
                detail: __(':count of :total devices have not checked in.', [
                    'count' => $fleet['offline'],
                    'total' => $fleet['total'],
                ]),
            ));
        }

        if ($admission['denial_rate'] >= self::DENIAL_RATE_ALERT) {
            $alerts->push(new OperationalAlertDTO(
                code: 'DENIAL_RATE_HIGH',
                severity: 'MEDIUM',
                subject: 'event',
                detail: __(':share of entry scans denied in the last :minutes minutes.', [
                    'share' => round($admission['denial_rate'] * 100).'%',
                    'minutes' => (int) (self::ARRIVAL_WINDOW_SECONDS / 60),
                ]),
            ));
        }

        foreach ($staffing['gaps'] as $gap) {
            $alerts->push(new OperationalAlertDTO(
                code: 'SHIFT_UNDERSTAFFED',
                severity: 'MEDIUM',
                subject: 'shift:'.$gap['shift_id'],
                detail: __(':position is short by :count.', [
                    'position' => $gap['position'],
                    'count' => $gap['required'] - $gap['filled'],
                ]),
            ));
        }

        return $alerts->sortBy(
            static fn (OperationalAlertDTO $alert): int => $alert->severity === 'HIGH' ? 0 : 1
        )->values();
    }
}
