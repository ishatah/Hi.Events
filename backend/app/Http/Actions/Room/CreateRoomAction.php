<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Room;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Room\UpsertRoomRequest;
use HiEvents\Resources\Room\RoomResource;
use HiEvents\Services\Application\Handlers\Room\CreateRoomHandler;
use Illuminate\Http\JsonResponse;

class CreateRoomAction extends BaseAction
{
    public function __construct(
        private readonly CreateRoomHandler $handler,
    ) {}

    public function __invoke(UpsertRoomRequest $request, int $venueId): JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($venueId),
            VenueDomainObject::class,
        );

        $record = $this->handler->handle($venueId, $request->validated());

        return $this->resourceResponse(RoomResource::class, $record, statusCode: 201);
    }

    private function resolveVenueId(int $venueId): int
    {
        return $venueId;
    }
}
