<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccreditationType;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\AccreditationType\DeleteAccreditationTypeHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteAccreditationTypeAction extends BaseAction
{
    public function __construct(
        private readonly DeleteAccreditationTypeHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $id): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->handler->handle($eventId, $id);

        return $this->deletedResponse();
    }
}
