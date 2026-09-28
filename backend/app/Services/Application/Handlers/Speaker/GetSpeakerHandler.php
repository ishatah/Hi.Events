<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Speaker;

use HiEvents\DomainObjects\Generated\SpeakerDomainObjectAbstract;
use HiEvents\DomainObjects\SpeakerDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\SpeakerRepositoryInterface;

class GetSpeakerHandler
{
    public function __construct(
        private readonly SpeakerRepositoryInterface $repository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $id): SpeakerDomainObject
    {
        $record = $this->repository->findFirstWhere([
            SpeakerDomainObjectAbstract::ID => $id,
            SpeakerDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($record === null) {
            throw new ResourceNotFoundException(__('The requested record could not be found.'));
        }

        return $record;
    }
}
