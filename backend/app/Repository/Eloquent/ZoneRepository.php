<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\ZoneDomainObject;
use HiEvents\Models\Zone;
use HiEvents\Repository\Interfaces\ZoneRepositoryInterface;

/**
 * @extends BaseRepository<ZoneDomainObject>
 */
class ZoneRepository extends BaseRepository implements ZoneRepositoryInterface
{
    protected function getModel(): string
    {
        return Zone::class;
    }

    public function getDomainObject(): string
    {
        return ZoneDomainObject::class;
    }
}
