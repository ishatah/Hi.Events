<?php

declare(strict_types=1);

namespace HiEvents\Jobs\Access;

use HiEvents\Services\Domain\Access\ZoneOccupancyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Keeps zone occupancy readable at a door without every scan recomputing it.
 *
 * Only zones belonging to live events with capacity enforcement are captured. A zone whose
 * event has finished, or whose rules never consult occupancy, would be pure cost.
 *
 * @see docs/arzo-master-plan/24-access-control.md
 */
class CaptureZoneOccupancySnapshotsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        DatabaseManager $databaseManager,
        ZoneOccupancyService $zoneOccupancyService,
    ): void {
        $zones = $databaseManager->table('zones')
            ->join('event_venues', 'event_venues.venue_id', '=', 'zones.venue_id')
            ->join('events', 'events.id', '=', 'event_venues.event_id')
            ->join('access_rules', 'access_rules.event_id', '=', 'events.id')
            ->where('events.status', 'LIVE')
            ->where('access_rules.enforce_capacity', true)
            ->where('access_rules.is_active', true)
            ->whereNull('access_rules.deleted_at')
            ->whereNull('zones.deleted_at')
            ->whereNull('events.deleted_at')
            ->distinct()
            ->select(['zones.id as zone_id', 'events.id as event_id', 'zones.capacity'])
            ->get();

        foreach ($zones as $zone) {
            $zoneOccupancyService->capture(
                zoneId: (int) $zone->zone_id,
                eventId: (int) $zone->event_id,
                capacity: $zone->capacity !== null ? (int) $zone->capacity : null,
            );
        }
    }
}
