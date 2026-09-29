<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\ExhibitorStaffDomainObject;
use HiEvents\Models\ExhibitorStaff;
use HiEvents\Repository\Interfaces\ExhibitorStaffRepositoryInterface;

/**
 * @extends BaseRepository<ExhibitorStaffDomainObject>
 */
class ExhibitorStaffRepository extends BaseRepository implements ExhibitorStaffRepositoryInterface
{
    protected function getModel(): string
    {
        return ExhibitorStaff::class;
    }

    public function getDomainObject(): string
    {
        return ExhibitorStaffDomainObject::class;
    }
}
