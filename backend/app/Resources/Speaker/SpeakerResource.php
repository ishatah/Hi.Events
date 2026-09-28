<?php

namespace HiEvents\Resources\Speaker;

use HiEvents\DomainObjects\SpeakerDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SpeakerDomainObject
 */
class SpeakerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'event_id' => $this->getEventId(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'email' => $this->getEmail(),
            'title' => $this->getTitle(),
            'company' => $this->getCompany(),
            'bio' => $this->getBio(),
            'is_published' => $this->getIsPublished(),
        ];
    }
}
