<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\BoothAssignmentDomainObject;
use HiEvents\Models\BoothAssignment;
use HiEvents\Repository\Interfaces\BoothAssignmentRepositoryInterface;

/**
 * @extends BaseRepository<BoothAssignmentDomainObject>
 */
class BoothAssignmentRepository extends BaseRepository implements BoothAssignmentRepositoryInterface
{
    protected function getModel(): string
    {
        return BoothAssignment::class;
    }

    public function getDomainObject(): string
    {
        return BoothAssignmentDomainObject::class;
    }
}
