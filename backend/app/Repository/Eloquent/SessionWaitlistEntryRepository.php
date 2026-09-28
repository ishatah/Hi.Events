<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SessionWaitlistEntryDomainObject;
use HiEvents\Models\SessionWaitlistEntry;
use HiEvents\Repository\Interfaces\SessionWaitlistEntryRepositoryInterface;

/**
 * @extends BaseRepository<SessionWaitlistEntryDomainObject>
 */
class SessionWaitlistEntryRepository extends BaseRepository implements SessionWaitlistEntryRepositoryInterface
{
    protected function getModel(): string
    {
        return SessionWaitlistEntry::class;
    }

    public function getDomainObject(): string
    {
        return SessionWaitlistEntryDomainObject::class;
    }
}
