<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Track;

use HiEvents\DomainObjects\Generated\TrackDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\TrackRepositoryInterface;

class DeleteTrackHandler
{
    public function __construct(
        private readonly TrackRepositoryInterface $repository,
        private readonly GetTrackHandler $getTrackHandler,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, int $id): void
    {
        $this->getTrackHandler->handle($eventId, $id);

        $this->repository->deleteWhere([
            TrackDomainObjectAbstract::ID => $id,
            TrackDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }
}
