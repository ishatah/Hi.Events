<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Track;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Track\UpsertTrackRequest;
use HiEvents\Resources\Track\TrackResource;
use HiEvents\Services\Application\Handlers\Track\CreateTrackHandler;
use Illuminate\Http\JsonResponse;

class CreateTrackAction extends BaseAction
{
    public function __construct(
        private readonly CreateTrackHandler $handler,
    ) {}

    public function __invoke(UpsertTrackRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $record = $this->handler->handle($eventId, $request->validated());

        return $this->resourceResponse(TrackResource::class, $record, statusCode: 201);
    }
}
