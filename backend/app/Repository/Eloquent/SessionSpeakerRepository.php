<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SessionSpeakerDomainObject;
use HiEvents\Models\SessionSpeaker;
use HiEvents\Repository\Interfaces\SessionSpeakerRepositoryInterface;

/**
 * @extends BaseRepository<SessionSpeakerDomainObject>
 */
class SessionSpeakerRepository extends BaseRepository implements SessionSpeakerRepositoryInterface
{
    protected function getModel(): string
    {
        return SessionSpeaker::class;
    }

    public function getDomainObject(): string
    {
        return SessionSpeakerDomainObject::class;
    }
}
