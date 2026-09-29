<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Exhibitor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Exhibitor\EventExhibitorResource;
use HiEvents\Services\Application\Handlers\Exhibitor\GetEventExhibitorsHandler;
use Illuminate\Http\JsonResponse;

class GetEventExhibitorsAction extends BaseAction
{
    public function __construct(
        private readonly GetEventExhibitorsHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::EXHIBITOR_MANAGE);

        return $this->jsonResponse([
            'data' => ($this->handler->handle($eventId))
                ->map(static fn (object $row): array => (new EventExhibitorResource($row))->toArray(request()))
                ->values()
                ->all(),
        ]);
    }
}
