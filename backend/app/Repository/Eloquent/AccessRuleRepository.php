<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccessRuleDomainObject;
use HiEvents\Models\AccessRule;
use HiEvents\Repository\Interfaces\AccessRuleRepositoryInterface;

/**
 * @extends BaseRepository<AccessRuleDomainObject>
 */
class AccessRuleRepository extends BaseRepository implements AccessRuleRepositoryInterface
{
    protected function getModel(): string
    {
        return AccessRule::class;
    }

    public function getDomainObject(): string
    {
        return AccessRuleDomainObject::class;
    }
}
