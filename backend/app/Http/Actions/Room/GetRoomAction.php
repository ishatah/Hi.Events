<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Room;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Room\RoomResource;
use HiEvents\Services\Application\Handlers\Room\GetRoomHandler;
use Illuminate\Http\JsonResponse;

class GetRoomAction extends BaseAction
{
    public function __construct(
        private readonly GetRoomHandler $handler,
    ) {}

    public function __invoke(int $venueId, int $id): JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($venueId),
            VenueDomainObject::class,
        );

        return $this->resourceResponse(RoomResource::class, $this->handler->handle($venueId, $id));
    }

    private function resolveVenueId(int $venueId): int
    {
        return $venueId;
    }
}
