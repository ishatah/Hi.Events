<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SessionAttendanceDomainObject;
use HiEvents\Models\SessionAttendance;
use HiEvents\Repository\Interfaces\SessionAttendanceRepositoryInterface;

/**
 * @extends BaseRepository<SessionAttendanceDomainObject>
 */
class SessionAttendanceRepository extends BaseRepository implements SessionAttendanceRepositoryInterface
{
    protected function getModel(): string
    {
        return SessionAttendance::class;
    }

    public function getDomainObject(): string
    {
        return SessionAttendanceDomainObject::class;
    }
}
