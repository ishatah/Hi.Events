<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Room;

use HiEvents\DomainObjects\Generated\RoomDomainObjectAbstract;
use HiEvents\DomainObjects\RoomDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\RoomRepositoryInterface;

class GetRoomHandler
{
    public function __construct(
        private readonly RoomRepositoryInterface $repository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $venueId, int $id): RoomDomainObject
    {
        $record = $this->repository->findFirstWhere([
            RoomDomainObjectAbstract::ID => $id,
            RoomDomainObjectAbstract::VENUE_ID => $venueId,
        ]);

        if ($record === null) {
            throw new ResourceNotFoundException(__('The requested record could not be found.'));
        }

        return $record;
    }
}
