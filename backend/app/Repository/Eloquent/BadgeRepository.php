<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\BadgeDomainObject;
use HiEvents\Models\Badge;
use HiEvents\Repository\Interfaces\BadgeRepositoryInterface;

/**
 * @extends BaseRepository<BadgeDomainObject>
 */
class BadgeRepository extends BaseRepository implements BadgeRepositoryInterface
{
    protected function getModel(): string
    {
        return Badge::class;
    }

    public function getDomainObject(): string
    {
        return BadgeDomainObject::class;
    }
}
