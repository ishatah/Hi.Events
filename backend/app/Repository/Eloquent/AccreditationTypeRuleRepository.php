<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccreditationTypeRuleDomainObject;
use HiEvents\Models\AccreditationTypeRule;
use HiEvents\Repository\Interfaces\AccreditationTypeRuleRepositoryInterface;

/**
 * @extends BaseRepository<AccreditationTypeRuleDomainObject>
 */
class AccreditationTypeRuleRepository extends BaseRepository implements AccreditationTypeRuleRepositoryInterface
{
    protected function getModel(): string
    {
        return AccreditationTypeRule::class;
    }

    public function getDomainObject(): string
    {
        return AccreditationTypeRuleDomainObject::class;
    }
}
