<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SpeakerDomainObject;
use HiEvents\Models\Speaker;
use HiEvents\Repository\Interfaces\SpeakerRepositoryInterface;

/**
 * @extends BaseRepository<SpeakerDomainObject>
 */
class SpeakerRepository extends BaseRepository implements SpeakerRepositoryInterface
{
    protected function getModel(): string
    {
        return Speaker::class;
    }

    public function getDomainObject(): string
    {
        return SpeakerDomainObject::class;
    }
}
