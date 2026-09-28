<?php

namespace HiEvents\Resources\Zone;

use HiEvents\DomainObjects\ZoneDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ZoneDomainObject
 */
class ZoneResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'venue_id' => $this->getVenueId(),
            'parent_zone_id' => $this->getParentZoneId(),
            'name' => $this->getName(),
            'code' => $this->getCode(),
            'zone_type' => $this->getZoneType(),
            'capacity' => $this->getCapacity(),
            'colour' => $this->getColour(),
            'requires_credential' => $this->getRequiresCredential(),
            'sort_order' => $this->getSortOrder(),
        ];
    }
}
