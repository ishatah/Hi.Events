<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Room;

use HiEvents\DomainObjects\Generated\RoomDomainObjectAbstract;
use HiEvents\DomainObjects\RoomDomainObject;
use HiEvents\Repository\Interfaces\RoomRepositoryInterface;
use Illuminate\Support\Str;

class CreateRoomHandler
{
    public function __construct(
        private readonly RoomRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $venueId, array $attributes): RoomDomainObject
    {
        return $this->repository->create(array_merge($attributes, [
            RoomDomainObjectAbstract::SHORT_ID => 'rm_'.Str::lower(Str::random(20)),
            RoomDomainObjectAbstract::VENUE_ID => $venueId,
        ]));
    }
}
