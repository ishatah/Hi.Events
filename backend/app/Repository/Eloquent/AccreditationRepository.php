<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccreditationDomainObject;
use HiEvents\Models\Accreditation;
use HiEvents\Repository\Interfaces\AccreditationRepositoryInterface;

/**
 * @extends BaseRepository<AccreditationDomainObject>
 */
class AccreditationRepository extends BaseRepository implements AccreditationRepositoryInterface
{
    protected function getModel(): string
    {
        return Accreditation::class;
    }

    public function getDomainObject(): string
    {
        return AccreditationDomainObject::class;
    }
}
