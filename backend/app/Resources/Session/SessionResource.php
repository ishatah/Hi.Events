<?php

namespace HiEvents\Resources\Session;

use HiEvents\DomainObjects\SessionDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SessionDomainObject
 */
class SessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'event_id' => $this->getEventId(),
            'event_occurrence_id' => $this->getEventOccurrenceId(),
            'track_id' => $this->getTrackId(),
            'room_id' => $this->getRoomId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'session_type' => $this->getSessionType(),
            'status' => $this->getStatus(),
            'starts_at' => $this->getStartsAt(),
            'ends_at' => $this->getEndsAt(),
            'timezone' => $this->getTimezone(),
            'capacity' => $this->getCapacity(),
            'requires_registration' => $this->getRequiresRegistration(),
            'allow_waitlist' => $this->getAllowWaitlist(),
            'check_in_enabled' => $this->getCheckInEnabled(),
            'is_published' => $this->getIsPublished(),
        ];
    }
}
