<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Track;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Track\DeleteTrackHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteTrackAction extends BaseAction
{
    public function __construct(
        private readonly DeleteTrackHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $id): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->handler->handle($eventId, $id);

        return $this->deletedResponse();
    }
}
