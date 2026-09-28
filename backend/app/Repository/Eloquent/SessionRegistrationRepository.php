<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SessionRegistrationDomainObject;
use HiEvents\Models\SessionRegistration;
use HiEvents\Repository\Interfaces\SessionRegistrationRepositoryInterface;

/**
 * @extends BaseRepository<SessionRegistrationDomainObject>
 */
class SessionRegistrationRepository extends BaseRepository implements SessionRegistrationRepositoryInterface
{
    protected function getModel(): string
    {
        return SessionRegistration::class;
    }

    public function getDomainObject(): string
    {
        return SessionRegistrationDomainObject::class;
    }
}
