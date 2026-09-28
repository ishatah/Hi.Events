<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Room;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Room\RoomResource;
use HiEvents\Services\Application\Handlers\Room\GetRoomsHandler;
use Illuminate\Http\JsonResponse;

class GetRoomsAction extends BaseAction
{
    public function __construct(
        private readonly GetRoomsHandler $handler,
    ) {}

    public function __invoke(int $venueId): JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($venueId),
            VenueDomainObject::class,
        );

        return $this->resourceResponse(RoomResource::class, $this->handler->handle($venueId));
    }

    private function resolveVenueId(int $venueId): int
    {
        return $venueId;
    }
}
