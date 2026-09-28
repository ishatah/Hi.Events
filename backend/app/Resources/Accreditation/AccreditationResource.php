<?php

namespace HiEvents\Resources\Accreditation;

use HiEvents\DomainObjects\AccreditationDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccreditationDomainObject
 */
class AccreditationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'event_id' => $this->getEventId(),
            'person_id' => $this->getPersonId(),
            'accreditation_type_id' => $this->getAccreditationTypeId(),
            'status' => $this->getStatus(),
            'submitted_at' => $this->getSubmittedAt(),
            'reviewed_at' => $this->getReviewedAt(),
            'rejection_reason' => $this->getRejectionReason(),
            'expires_at' => $this->getExpiresAt(),
        ];
    }
}
