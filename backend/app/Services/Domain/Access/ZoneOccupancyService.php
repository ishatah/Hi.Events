<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Access;

use HiEvents\DomainObjects\Enums\AccessDirection;
use HiEvents\DomainObjects\Enums\AccessResult;
use Illuminate\Database\DatabaseManager;

/**
 * How many credential holders are currently inside a zone.
 *
 * Derived from the access log rather than stored on the zone: a counter drifts as soon as
 * anything arrives out of order, and offline replay guarantees that. The cost of deriving
 * it is the problem instead, so the aggregation happens in SQL and is read from a snapshot
 * when one is fresh enough.
 *
 * @see docs/arzo-master-plan/24-access-control.md
 */
class ZoneOccupancyService
{
    private const SNAPSHOT_FRESHNESS_SECONDS = 30;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * A door decision tolerates a slightly stale number — a zone at its limit is
     * re-checked within seconds, and being one person out on a 5,000-capacity hall does
     * not change the verdict. Recomputing the whole history on every scan does.
     */
    public function current(int $zoneId, int $eventId): int
    {
        $snapshot = $this->databaseManager->table('zone_occupancy_snapshots')
            ->where('zone_id', $zoneId)
            ->where('event_id', $eventId)
            ->where('captured_at', '>', now()->subSeconds(self::SNAPSHOT_FRESHNESS_SECONDS))
            ->orderByDesc('captured_at')
            ->value('occupancy');

        if ($snapshot !== null) {
            return (int) $snapshot;
        }

        return $this->recompute($zoneId, $eventId);
    }

    /**
     * Counts in SQL. The previous implementation pulled one row per credential into PHP and
     * filtered there, so memory and time grew with the zone's whole history rather than
     * with the number of people inside it.
     */
    public function recompute(int $zoneId, int $eventId): int
    {
        $inside = $this->databaseManager->table('access_logs')
            ->selectRaw('credential_id')
            ->where('zone_id', $zoneId)
            ->where('event_id', $eventId)
            ->where('result', AccessResult::GRANTED->value)
            ->whereNotNull('credential_id')
            ->groupBy('credential_id')
            ->havingRaw(
                'SUM(CASE WHEN direction = ? THEN -1 ELSE 1 END) > 0',
                [AccessDirection::EXIT->value]
            );

        return (int) $this->databaseManager->query()
            ->fromSub($inside, 'inside')
            ->count();
    }

    public function capture(int $zoneId, int $eventId, ?int $capacity = null): int
    {
        $occupancy = $this->recompute($zoneId, $eventId);

        $this->databaseManager->table('zone_occupancy_snapshots')->insert([
            'zone_id' => $zoneId,
            'event_id' => $eventId,
            'occupancy' => $occupancy,
            'capacity' => $capacity,
            'captured_at' => now(),
        ]);

        return $occupancy;
    }

    /**
     * Whether occupancy is worth computing for this scan. Most zones have no limit at all,
     * and for those the count is pure cost on every scan.
     *
     * A capacity set on the zone itself counts as an instruction to enforce it — a hall
     * marked as holding 500 people that quietly admits 600 would be a safety problem, not a
     * configuration subtlety. A rule carrying enforce_capacity also counts, since it may
     * cap a zone that has no standing limit of its own.
     */
    public function isEnforced(int $eventId, ?int $zoneId = null): bool
    {
        if ($zoneId !== null) {
            $zoneCapacity = $this->databaseManager->table('zones')
                ->where('id', $zoneId)
                ->whereNull('deleted_at')
                ->value('capacity');

            if ($zoneCapacity !== null) {
                return true;
            }
        }

        return $this->databaseManager->table('access_rules')
            ->where('event_id', $eventId)
            ->where('enforce_capacity', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();
    }
}
