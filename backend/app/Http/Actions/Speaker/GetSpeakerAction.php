<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Speaker;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Speaker\SpeakerResource;
use HiEvents\Services\Application\Handlers\Speaker\GetSpeakerHandler;
use Illuminate\Http\JsonResponse;

class GetSpeakerAction extends BaseAction
{
    public function __construct(
        private readonly GetSpeakerHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $id): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(SpeakerResource::class, $this->handler->handle($eventId, $id));
    }
}
