<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Exhibitor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Exhibitor\LeadResource;
use HiEvents\Services\Application\Handlers\Exhibitor\GetLeadsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetLeadsAction extends BaseAction
{
    public function __construct(
        private readonly GetLeadsHandler $handler,
    ) {}

    public function __invoke(Request $request, int $eventId, int $eventExhibitorId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::LEAD_VIEW);

        return $this->jsonResponse([
            'data' => ($this->handler->handle(
                $eventExhibitorId,
                $request->query('rating') !== null ? (string) $request->query('rating') : null,
                $request->query('status') !== null ? (string) $request->query('status') : null,
            ))
                ->map(static fn (object $row): array => (new LeadResource($row))->toArray(request()))
                ->values()
                ->all(),
        ]);
    }
}
