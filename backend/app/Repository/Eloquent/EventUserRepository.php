<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventUserDomainObject;
use HiEvents\Models\EventUser;
use HiEvents\Repository\Interfaces\EventUserRepositoryInterface;

/**
 * @extends BaseRepository<EventUserDomainObject>
 */
class EventUserRepository extends BaseRepository implements EventUserRepositoryInterface
{
    protected function getModel(): string
    {
        return EventUser::class;
    }

    public function getDomainObject(): string
    {
        return EventUserDomainObject::class;
    }
}
