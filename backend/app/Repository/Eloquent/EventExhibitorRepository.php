<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventExhibitorDomainObject;
use HiEvents\Models\EventExhibitor;
use HiEvents\Repository\Interfaces\EventExhibitorRepositoryInterface;

/**
 * @extends BaseRepository<EventExhibitorDomainObject>
 */
class EventExhibitorRepository extends BaseRepository implements EventExhibitorRepositoryInterface
{
    protected function getModel(): string
    {
        return EventExhibitor::class;
    }

    public function getDomainObject(): string
    {
        return EventExhibitorDomainObject::class;
    }
}
