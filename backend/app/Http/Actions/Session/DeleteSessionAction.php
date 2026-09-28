<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Session;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Session\DeleteSessionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteSessionAction extends BaseAction
{
    public function __construct(
        private readonly DeleteSessionHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $id): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->handler->handle($eventId, $id);

        return $this->deletedResponse();
    }
}
