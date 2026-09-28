<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Track;

use HiEvents\DomainObjects\Generated\TrackDomainObjectAbstract;
use HiEvents\DomainObjects\TrackDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\TrackRepositoryInterface;

class GetTrackHandler
{
    public function __construct(
        private readonly TrackRepositoryInterface $repository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $id): TrackDomainObject
    {
        $record = $this->repository->findFirstWhere([
            TrackDomainObjectAbstract::ID => $id,
            TrackDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($record === null) {
            throw new ResourceNotFoundException(__('The requested record could not be found.'));
        }

        return $record;
    }
}
