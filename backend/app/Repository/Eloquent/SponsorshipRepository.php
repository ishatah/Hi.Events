<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SponsorshipDomainObject;
use HiEvents\Models\Sponsorship;
use HiEvents\Repository\Interfaces\SponsorshipRepositoryInterface;

/**
 * @extends BaseRepository<SponsorshipDomainObject>
 */
class SponsorshipRepository extends BaseRepository implements SponsorshipRepositoryInterface
{
    protected function getModel(): string
    {
        return Sponsorship::class;
    }

    public function getDomainObject(): string
    {
        return SponsorshipDomainObject::class;
    }
}
