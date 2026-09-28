<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\RsvpResponseDomainObject;
use HiEvents\Models\RsvpResponse;
use HiEvents\Repository\Interfaces\RsvpResponseRepositoryInterface;

/**
 * @extends BaseRepository<RsvpResponseDomainObject>
 */
class RsvpResponseRepository extends BaseRepository implements RsvpResponseRepositoryInterface
{
    protected function getModel(): string
    {
        return RsvpResponse::class;
    }

    public function getDomainObject(): string
    {
        return RsvpResponseDomainObject::class;
    }
}
