<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Track;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Track\TrackResource;
use HiEvents\Services\Application\Handlers\Track\GetTrackHandler;
use Illuminate\Http\JsonResponse;

class GetTrackAction extends BaseAction
{
    public function __construct(
        private readonly GetTrackHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $id): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(TrackResource::class, $this->handler->handle($eventId, $id));
    }
}
