<?php

namespace HiEvents\Resources\Room;

use HiEvents\DomainObjects\RoomDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoomDomainObject
 */
class RoomResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'venue_id' => $this->getVenueId(),
            'floor_id' => $this->getFloorId(),
            'zone_id' => $this->getZoneId(),
            'name' => $this->getName(),
            'code' => $this->getCode(),
            'capacity' => $this->getCapacity(),
            'room_type' => $this->getRoomType(),
        ];
    }
}
