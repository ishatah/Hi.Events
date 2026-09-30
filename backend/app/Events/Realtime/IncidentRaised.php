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
 * An incident was reported or changed severity.
 *
 * @see docs/arzo-master-plan/60-incident-management.md
 */
class IncidentRaised implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $eventId,
        public readonly int $incidentId,
        public readonly string $reference,
        public readonly string $title,
        public readonly string $severity,
        public readonly string $status,
        public readonly ?int $zoneId = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(sprintf('event.%d.incidents', $this->eventId))];
    }

    public function broadcastAs(): string
    {
        return 'incident.raised';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incidentId,
            'reference' => $this->reference,
            'title' => $this->title,
            'severity' => $this->severity,
            'status' => $this->status,
            'zone_id' => $this->zoneId,
        ];
    }
}
