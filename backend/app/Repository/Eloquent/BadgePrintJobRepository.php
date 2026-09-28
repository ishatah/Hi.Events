<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\BadgePrintJobDomainObject;
use HiEvents\Models\BadgePrintJob;
use HiEvents\Repository\Interfaces\BadgePrintJobRepositoryInterface;

/**
 * @extends BaseRepository<BadgePrintJobDomainObject>
 */
class BadgePrintJobRepository extends BaseRepository implements BadgePrintJobRepositoryInterface
{
    protected function getModel(): string
    {
        return BadgePrintJob::class;
    }

    public function getDomainObject(): string
    {
        return BadgePrintJobDomainObject::class;
    }
}
