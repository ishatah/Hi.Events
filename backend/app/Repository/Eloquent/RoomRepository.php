<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\RoomDomainObject;
use HiEvents\Models\Room;
use HiEvents\Repository\Interfaces\RoomRepositoryInterface;

/**
 * @extends BaseRepository<RoomDomainObject>
 */
class RoomRepository extends BaseRepository implements RoomRepositoryInterface
{
    protected function getModel(): string
    {
        return Room::class;
    }

    public function getDomainObject(): string
    {
        return RoomDomainObject::class;
    }
}
