<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Room;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Room\UpsertRoomRequest;
use HiEvents\Resources\Room\RoomResource;
use HiEvents\Services\Application\Handlers\Room\UpdateRoomHandler;
use Illuminate\Http\JsonResponse;

class UpdateRoomAction extends BaseAction
{
    public function __construct(
        private readonly UpdateRoomHandler $handler,
    ) {}

    public function __invoke(UpsertRoomRequest $request, int $venueId, int $id): JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($venueId),
            VenueDomainObject::class,
        );

        $record = $this->handler->handle($venueId, $id, $request->validated());

        return $this->resourceResponse(RoomResource::class, $record);
    }

    private function resolveVenueId(int $venueId): int
    {
        return $venueId;
    }
}
