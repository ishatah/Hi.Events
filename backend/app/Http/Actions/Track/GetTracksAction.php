<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Track;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Track\TrackResource;
use HiEvents\Services\Application\Handlers\Track\GetTracksHandler;
use Illuminate\Http\JsonResponse;

class GetTracksAction extends BaseAction
{
    public function __construct(
        private readonly GetTracksHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(TrackResource::class, $this->handler->handle($eventId));
    }
}
