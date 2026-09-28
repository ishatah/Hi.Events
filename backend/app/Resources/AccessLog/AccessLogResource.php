<?php

namespace HiEvents\Resources\AccessLog;

use HiEvents\DomainObjects\AccessLogDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccessLogDomainObject
 */
class AccessLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'event_id' => $this->getEventId(),
            'credential_id' => $this->getCredentialId(),
            'access_point_id' => $this->getAccessPointId(),
            'zone_id' => $this->getZoneId(),
            'occurred_at' => $this->getOccurredAt(),
            'recorded_at' => $this->getRecordedAt(),
            'direction' => $this->getDirection(),
            'result' => $this->getResult(),
            'identifier_type' => $this->getIdentifierType(),
            'is_offline_replay' => $this->getIsOfflineReplay(),
            'source' => $this->getSource(),
        ];
    }
}
