<?php

namespace HiEvents\Resources\Credential;

use HiEvents\DomainObjects\CredentialDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CredentialDomainObject
 */
class CredentialResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'event_id' => $this->getEventId(),
            'person_id' => $this->getPersonId(),
            'attendee_id' => $this->getAttendeeId(),
            'accreditation_id' => $this->getAccreditationId(),
            'credential_type' => $this->getCredentialType(),
            'status' => $this->getStatus(),
            'issued_at' => $this->getIssuedAt(),
            'valid_from' => $this->getValidFrom(),
            'valid_until' => $this->getValidUntil(),
            'revoked_at' => $this->getRevokedAt(),
        ];
    }
}
