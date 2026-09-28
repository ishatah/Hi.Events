<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\TrackDomainObject;
use HiEvents\Models\Track;
use HiEvents\Repository\Interfaces\TrackRepositoryInterface;

/**
 * @extends BaseRepository<TrackDomainObject>
 */
class TrackRepository extends BaseRepository implements TrackRepositoryInterface
{
    protected function getModel(): string
    {
        return Track::class;
    }

    public function getDomainObject(): string
    {
        return TrackDomainObject::class;
    }
}
