<?php

namespace HiEvents\Resources\Venue;

use HiEvents\DomainObjects\VenueDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VenueDomainObject
 */
class VenueResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'name' => $this->getName(),
            'timezone' => $this->getTimezone(),
            'organizer_id' => $this->getOrganizerId(),
            'location_id' => $this->getLocationId(),
            'default_capacity' => $this->getDefaultCapacity(),
            'notes' => $this->getNotes(),
        ];
    }
}
