<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SponsorshipEntitlementDomainObject;
use HiEvents\Models\SponsorshipEntitlement;
use HiEvents\Repository\Interfaces\SponsorshipEntitlementRepositoryInterface;

/**
 * @extends BaseRepository<SponsorshipEntitlementDomainObject>
 */
class SponsorshipEntitlementRepository extends BaseRepository implements SponsorshipEntitlementRepositoryInterface
{
    protected function getModel(): string
    {
        return SponsorshipEntitlement::class;
    }

    public function getDomainObject(): string
    {
        return SponsorshipEntitlementDomainObject::class;
    }
}
