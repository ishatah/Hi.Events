<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Room;

use HiEvents\DomainObjects\Generated\RoomDomainObjectAbstract;
use HiEvents\Repository\Interfaces\RoomRepositoryInterface;
use Illuminate\Support\Collection;

class GetRoomsHandler
{
    public function __construct(
        private readonly RoomRepositoryInterface $repository,
    ) {}

    public function handle(int $venueId): Collection
    {
        return $this->repository->findWhere([
            RoomDomainObjectAbstract::VENUE_ID => $venueId,
        ]);
    }
}
