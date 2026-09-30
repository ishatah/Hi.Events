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
 * A device came online, went quiet, or reported a problem.
 *
 * Only state changes broadcast. A message per heartbeat from every device would be traffic
 * with no reader, which is the same reason health events are only appended on change.
 *
 * @see docs/arzo-master-plan/40-device-management.md
 */
class DeviceStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $eventId,
        public readonly int $deviceId,
        public readonly string $name,
        public readonly string $state,
        public readonly ?string $reason = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(sprintf('event.%d.devices', $this->eventId))];
    }

    public function broadcastAs(): string
    {
        return 'device.state';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->deviceId,
            'name' => $this->name,
            'state' => $this->state,
            'reason' => $this->reason,
        ];
    }
}
