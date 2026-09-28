<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccessLogDomainObject;
use HiEvents\Models\AccessLog;
use HiEvents\Repository\Interfaces\AccessLogRepositoryInterface;

/**
 * @extends BaseRepository<AccessLogDomainObject>
 */
class AccessLogRepository extends BaseRepository implements AccessLogRepositoryInterface
{
    protected function getModel(): string
    {
        return AccessLog::class;
    }

    public function getDomainObject(): string
    {
        return AccessLogDomainObject::class;
    }
}
