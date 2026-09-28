<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccessLog;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\AccessLog\AccessLogResource;
use HiEvents\Services\Application\Handlers\AccessLog\GetAccessLogsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAccessLogsAction extends BaseAction
{
    public function __construct(
        private readonly GetAccessLogsHandler $handler,
    ) {}

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: AccessLogResource::class,
            data: $this->handler->handle($eventId, $this->getPaginationQueryParams($request)),
        );
    }
}
