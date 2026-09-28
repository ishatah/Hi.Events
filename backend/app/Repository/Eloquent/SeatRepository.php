<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SeatDomainObject;
use HiEvents\Models\Seat;
use HiEvents\Repository\Interfaces\SeatRepositoryInterface;

/**
 * @extends BaseRepository<SeatDomainObject>
 */
class SeatRepository extends BaseRepository implements SeatRepositoryInterface
{
    protected function getModel(): string
    {
        return Seat::class;
    }

    public function getDomainObject(): string
    {
        return SeatDomainObject::class;
    }
}
