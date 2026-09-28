<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Speaker;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Speaker\SpeakerResource;
use HiEvents\Services\Application\Handlers\Speaker\GetSpeakersHandler;
use Illuminate\Http\JsonResponse;

class GetSpeakersAction extends BaseAction
{
    public function __construct(
        private readonly GetSpeakersHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(SpeakerResource::class, $this->handler->handle($eventId));
    }
}
