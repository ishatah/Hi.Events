<?php

declare(strict_types=1);

namespace HiEvents\Events\Realtime;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A zone's occupancy crossed a threshold worth showing.
 *
 * Broadcast on threshold rather than on every scan: a hall filling one person at a time
 * produces thousands of messages that say nothing a board could act on.
 *
 * @see docs/arzo-master-plan/24-access-control.md
 */
class ZoneOccupancyChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $eventId,
        public readonly int $zoneId,
        public readonly int $occupancy,
        public readonly ?int $capacity,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(sprintf('event.%d.occupancy', $this->eventId))];
    }

    public function broadcastAs(): string
    {
        return 'zone.occupancy';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'zone_id' => $this->zoneId,
            'occupancy' => $this->occupancy,
            'capacity' => $this->capacity,
            'utilisation' => $this->capacity !== null && $this->capacity > 0
                ? round($this->occupancy / $this->capacity, 4)
                : null,
        ];
    }
}
