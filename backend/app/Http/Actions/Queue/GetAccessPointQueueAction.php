<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Queue;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Queue\QueueEstimationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAccessPointQueueAction extends BaseAction
{
    public function __construct(
        private readonly QueueEstimationService $queueEstimationService,
    ) {}

    public function __invoke(Request $request, int $eventId, int $accessPointId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::ACCESS_LOGS_VIEW);

        $windowSeconds = min(3600, max(60, (int) ($request->query('window_seconds') ?? 300)));

        $estimate = $this->queueEstimationService->estimate($accessPointId, $windowSeconds);

        return $this->jsonResponse($estimate->toArray() + [
            'projection' => $this->queueEstimationService->projectDepth($accessPointId),
            'suggests_misconfiguration' => $estimate->suggestsMisconfiguration(),
            'are_estimates' => true,
        ]);
    }
}
