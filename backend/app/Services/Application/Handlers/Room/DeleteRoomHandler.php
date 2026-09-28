<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Room;

use HiEvents\DomainObjects\Generated\RoomDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\RoomRepositoryInterface;

class DeleteRoomHandler
{
    public function __construct(
        private readonly RoomRepositoryInterface $repository,
        private readonly GetRoomHandler $getRoomHandler,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $venueId, int $id): void
    {
        $this->getRoomHandler->handle($venueId, $id);

        $this->repository->deleteWhere([
            RoomDomainObjectAbstract::ID => $id,
            RoomDomainObjectAbstract::VENUE_ID => $venueId,
        ]);
    }
}
