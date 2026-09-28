<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Speaker;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Speaker\UpsertSpeakerRequest;
use HiEvents\Resources\Speaker\SpeakerResource;
use HiEvents\Services\Application\Handlers\Speaker\CreateSpeakerHandler;
use Illuminate\Http\JsonResponse;

class CreateSpeakerAction extends BaseAction
{
    public function __construct(
        private readonly CreateSpeakerHandler $handler,
    ) {}

    public function __invoke(UpsertSpeakerRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $record = $this->handler->handle($eventId, $request->validated());

        return $this->resourceResponse(SpeakerResource::class, $record, statusCode: 201);
    }
}
