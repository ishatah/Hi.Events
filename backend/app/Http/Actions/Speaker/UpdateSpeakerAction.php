<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Speaker;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Speaker\UpsertSpeakerRequest;
use HiEvents\Resources\Speaker\SpeakerResource;
use HiEvents\Services\Application\Handlers\Speaker\UpdateSpeakerHandler;
use Illuminate\Http\JsonResponse;

class UpdateSpeakerAction extends BaseAction
{
    public function __construct(
        private readonly UpdateSpeakerHandler $handler,
    ) {}

    public function __invoke(UpsertSpeakerRequest $request, int $eventId, int $id): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $record = $this->handler->handle($eventId, $id, $request->validated());

        return $this->resourceResponse(SpeakerResource::class, $record);
    }
}
