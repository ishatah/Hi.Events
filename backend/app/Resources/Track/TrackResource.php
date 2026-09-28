<?php

namespace HiEvents\Resources\Track;

use HiEvents\DomainObjects\TrackDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrackDomainObject
 */
class TrackResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'event_id' => $this->getEventId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'colour' => $this->getColour(),
            'sort_order' => $this->getSortOrder(),
        ];
    }
}
