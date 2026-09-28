<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Room;

use HiEvents\DomainObjects\Generated\RoomDomainObjectAbstract;
use HiEvents\DomainObjects\RoomDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\RoomRepositoryInterface;

class UpdateRoomHandler
{
    public function __construct(
        private readonly RoomRepositoryInterface $repository,
        private readonly GetRoomHandler $getRoomHandler,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ResourceConflictException
     */
    public function handle(int $venueId, int $id, array $attributes): RoomDomainObject
    {
        // Scoped read first, so an id under another parent cannot be updated.
        $this->getRoomHandler->handle($venueId, $id);

        $this->repository->updateWhere(
            attributes: $attributes,
            where: [
                RoomDomainObjectAbstract::ID => $id,
                RoomDomainObjectAbstract::VENUE_ID => $venueId,
            ],
        );

        return $this->getRoomHandler->handle($venueId, $id);
    }
}
