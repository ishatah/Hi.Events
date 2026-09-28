<?php

namespace HiEvents\Resources\AccessPoint;

use HiEvents\DomainObjects\AccessPointDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccessPointDomainObject
 */
class AccessPointResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'zone_id' => $this->getZoneId(),
            'name' => $this->getName(),
            'code' => $this->getCode(),
            'direction' => $this->getDirection(),
            'access_point_type' => $this->getAccessPointType(),
            'is_active' => $this->getIsActive(),
        ];
    }
}
