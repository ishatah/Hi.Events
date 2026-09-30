<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects${n}DomainObject;
use HiEvents\Models${n};
use HiEvents\Repository\Interfaces${n}RepositoryInterface;

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
