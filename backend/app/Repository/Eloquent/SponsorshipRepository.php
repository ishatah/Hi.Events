<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects${n}DomainObject;
use HiEvents\Models${n};
use HiEvents\Repository\Interfaces${n}RepositoryInterface;

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
