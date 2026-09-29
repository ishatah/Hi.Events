<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\LeadConsentDomainObject;
use HiEvents\Models\LeadConsent;
use HiEvents\Repository\Interfaces\LeadConsentRepositoryInterface;

/**
 * @extends BaseRepository<LeadConsentDomainObject>
 */
class LeadConsentRepository extends BaseRepository implements LeadConsentRepositoryInterface
{
    protected function getModel(): string
    {
        return LeadConsent::class;
    }

    public function getDomainObject(): string
    {
        return LeadConsentDomainObject::class;
    }
}
