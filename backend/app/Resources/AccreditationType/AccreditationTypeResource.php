<?php

namespace HiEvents\Resources\AccreditationType;

use HiEvents\DomainObjects\AccreditationTypeDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccreditationTypeDomainObject
 */
class AccreditationTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'event_id' => $this->getEventId(),
            'code' => $this->getCode(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'colour' => $this->getColour(),
            'requires_approval' => $this->getRequiresApproval(),
            'requires_photo' => $this->getRequiresPhoto(),
            'requires_id_document' => $this->getRequiresIdDocument(),
            'max_issuable' => $this->getMaxIssuable(),
            'badge_template_id' => $this->getBadgeTemplateId(),
            'is_active' => $this->getIsActive(),
        ];
    }
}
