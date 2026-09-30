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
 * A scan happened at a door.
 *
 * Deliberately thin: an identifier, a verdict and a place. A live board does not need the
 * holder's details, and pushing personal data down a channel several operators are watching
 * would put it on more screens than the decision requires.
 *
 * @see docs/arzo-master-plan/71-realtime-architecture.md
 */
class AccessScanRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $eventId,
        public readonly ?int $zoneId,
        public readonly int $accessPointId,
        public readonly string $result,
        public readonly bool $granted,
        public readonly string $occurredAt,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(sprintf('event.%d.access', $this->eventId))];
    }

    public function broadcastAs(): string
    {
        return 'access.scan';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'zone_id' => $this->zoneId,
            'access_point_id' => $this->accessPointId,
            'result' => $this->result,
            'granted' => $this->granted,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
