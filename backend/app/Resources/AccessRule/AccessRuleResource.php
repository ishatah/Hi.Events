<?php

namespace HiEvents\Resources\AccessRule;

use HiEvents\DomainObjects\AccessRuleDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccessRuleDomainObject
 */
class AccessRuleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'event_id' => $this->getEventId(),
            'name' => $this->getName(),
            'priority' => $this->getPriority(),
            'effect' => $this->getEffect(),
            'subject_type' => $this->getSubjectType(),
            'subject_id' => $this->getSubjectId(),
            'target_type' => $this->getTargetType(),
            'target_id' => $this->getTargetId(),
            'starts_at' => $this->getStartsAt(),
            'ends_at' => $this->getEndsAt(),
            'time_from' => $this->getTimeFrom(),
            'time_to' => $this->getTimeTo(),
            'max_entries' => $this->getMaxEntries(),
            'allow_reentry' => $this->getAllowReentry(),
            'min_reentry_seconds' => $this->getMinReentrySeconds(),
            'enforce_capacity' => $this->getEnforceCapacity(),
            'is_active' => $this->getIsActive(),
        ];
    }
}
