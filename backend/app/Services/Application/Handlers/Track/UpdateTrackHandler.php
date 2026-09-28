<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Track;

use HiEvents\DomainObjects\Generated\TrackDomainObjectAbstract;
use HiEvents\DomainObjects\TrackDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\TrackRepositoryInterface;

class UpdateTrackHandler
{
    public function __construct(
        private readonly TrackRepositoryInterface $repository,
        private readonly GetTrackHandler $getTrackHandler,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, int $id, array $attributes): TrackDomainObject
    {
        // Scoped read first, so an id belonging to another tenant cannot be updated.
        $this->getTrackHandler->handle($eventId, $id);

        $this->repository->updateWhere(
            attributes: $attributes,
            where: [
                TrackDomainObjectAbstract::ID => $id,
                TrackDomainObjectAbstract::EVENT_ID => $eventId,
            ],
        );

        return $this->getTrackHandler->handle($eventId, $id);
    }
}
