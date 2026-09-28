<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\BoothDomainObject;
use HiEvents\Models\Booth;
use HiEvents\Repository\Interfaces\BoothRepositoryInterface;

/**
 * @extends BaseRepository<BoothDomainObject>
 */
class BoothRepository extends BaseRepository implements BoothRepositoryInterface
{
    protected function getModel(): string
    {
        return Booth::class;
    }

    public function getDomainObject(): string
    {
        return BoothDomainObject::class;
    }
}
