<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Track;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Track\UpsertTrackRequest;
use HiEvents\Resources\Track\TrackResource;
use HiEvents\Services\Application\Handlers\Track\UpdateTrackHandler;
use Illuminate\Http\JsonResponse;

class UpdateTrackAction extends BaseAction
{
    public function __construct(
        private readonly UpdateTrackHandler $handler,
    ) {}

    public function __invoke(UpsertTrackRequest $request, int $eventId, int $id): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $record = $this->handler->handle($eventId, $id, $request->validated());

        return $this->resourceResponse(TrackResource::class, $record);
    }
}
