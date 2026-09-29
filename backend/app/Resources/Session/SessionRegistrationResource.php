<?php

namespace HiEvents\Resources\Session;

use HiEvents\DomainObjects\SessionRegistrationDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SessionRegistrationDomainObject
 */
class SessionRegistrationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'session_id' => $this->getSessionId(),
            'attendee_id' => $this->getAttendeeId(),
            'status' => $this->getStatus(),
            'registered_at' => $this->getRegisteredAt(),
            'cancelled_at' => $this->getCancelledAt(),
        ];
    }
}
