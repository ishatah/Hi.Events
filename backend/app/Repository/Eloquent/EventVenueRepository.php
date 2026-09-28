<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventVenueDomainObject;
use HiEvents\Models\EventVenue;
use HiEvents\Repository\Interfaces\EventVenueRepositoryInterface;

/**
 * @extends BaseRepository<EventVenueDomainObject>
 */
class EventVenueRepository extends BaseRepository implements EventVenueRepositoryInterface
{
    protected function getModel(): string
    {
        return EventVenue::class;
    }

    public function getDomainObject(): string
    {
        return EventVenueDomainObject::class;
    }
}
