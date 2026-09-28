<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccreditationTypeDomainObject;
use HiEvents\Models\AccreditationType;
use HiEvents\Repository\Interfaces\AccreditationTypeRepositoryInterface;

/**
 * @extends BaseRepository<AccreditationTypeDomainObject>
 */
class AccreditationTypeRepository extends BaseRepository implements AccreditationTypeRepositoryInterface
{
    protected function getModel(): string
    {
        return AccreditationType::class;
    }

    public function getDomainObject(): string
    {
        return AccreditationTypeDomainObject::class;
    }
}
