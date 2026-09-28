<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SessionDomainObject;
use HiEvents\Models\Session;
use HiEvents\Repository\Interfaces\SessionRepositoryInterface;

/**
 * @extends BaseRepository<SessionDomainObject>
 */
class SessionRepository extends BaseRepository implements SessionRepositoryInterface
{
    protected function getModel(): string
    {
        return Session::class;
    }

    public function getDomainObject(): string
    {
        return SessionDomainObject::class;
    }
}
