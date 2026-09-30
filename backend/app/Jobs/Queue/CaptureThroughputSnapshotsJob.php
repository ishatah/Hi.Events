<?php

declare(strict_types=1);

namespace HiEvents\Jobs\Queue;

use HiEvents\Services\Domain\Queue\QueueEstimationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Records one throughput window per door of every live event.
 *
 * Snapshots rather than recomputing on request, because the historical arrival curve is
 * what makes staffing planning and post-event analysis possible — recomputing would answer
 * "now" and lose the shape of the day.
 *
 * Only live events are captured: a finished event's doors would write empty rows forever.
 *
 * @see docs/arzo-master-plan/20-queue-management.md
 */
class CaptureThroughputSnapshotsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const WINDOW_SECONDS = 60;

    public function handle(
        DatabaseManager $databaseManager,
        QueueEstimationService $queueEstimationService,
    ): void {
        $accessPoints = $databaseManager->table('access_points')
            ->join('zones', 'zones.id', '=', 'access_points.zone_id')
            ->join('event_venues', 'event_venues.venue_id', '=', 'zones.venue_id')
            ->join('events', 'events.id', '=', 'event_venues.event_id')
            ->where('events.status', 'LIVE')
            ->where('access_points.is_active', true)
            ->whereNull('access_points.deleted_at')
            ->whereNull('zones.deleted_at')
            ->whereNull('events.deleted_at')
            ->distinct()
            ->select(['access_points.id as access_point_id', 'events.id as event_id'])
            ->get();

        foreach ($accessPoints as $accessPoint) {
            $queueEstimationService->capture(
                accessPointId: (int) $accessPoint->access_point_id,
                eventId: (int) $accessPoint->event_id,
                windowSeconds: self::WINDOW_SECONDS,
            );
        }
    }
}
