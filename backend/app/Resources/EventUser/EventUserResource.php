<?php

namespace HiEvents\Resources\EventUser;

use HiEvents\DomainObjects\EventUserDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventUserDomainObject
 */
class EventUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'event_id' => $this->getEventId(),
            'user_id' => $this->getUserId(),
            'permission_role_id' => $this->getPermissionRoleId(),
            'granted_by' => $this->getGrantedBy(),
            'granted_at' => $this->getGrantedAt(),
            'expires_at' => $this->getExpiresAt(),
        ];
    }
}
