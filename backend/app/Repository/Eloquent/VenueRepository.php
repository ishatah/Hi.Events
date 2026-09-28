<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Models\Venue;
use HiEvents\Repository\Interfaces\VenueRepositoryInterface;

/**
 * @extends BaseRepository<VenueDomainObject>
 */
class VenueRepository extends BaseRepository implements VenueRepositoryInterface
{
    protected function getModel(): string
    {
        return Venue::class;
    }

    public function getDomainObject(): string
    {
        return VenueDomainObject::class;
    }
}
