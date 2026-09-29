<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\LeadDomainObject;
use HiEvents\Models\Lead;
use HiEvents\Repository\Interfaces\LeadRepositoryInterface;

/**
 * @extends BaseRepository<LeadDomainObject>
 */
class LeadRepository extends BaseRepository implements LeadRepositoryInterface
{
    protected function getModel(): string
    {
        return Lead::class;
    }

    public function getDomainObject(): string
    {
        return LeadDomainObject::class;
    }
}
