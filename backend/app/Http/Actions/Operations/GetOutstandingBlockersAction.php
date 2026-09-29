<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Operations;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Operations\TaskTemplateService;
use Illuminate\Http\JsonResponse;

class GetOutstandingBlockersAction extends BaseAction
{
    public function __construct(
        private readonly TaskTemplateService $taskTemplateService,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::EVENT_VIEW);

        return $this->jsonResponse([
            'blockers' => $this->taskTemplateService->outstandingBlockers($eventId),
        ]);
    }
}
