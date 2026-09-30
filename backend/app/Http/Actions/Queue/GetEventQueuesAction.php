<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Queue;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Queue\DTO\QueueEstimateDTO;
use HiEvents\Services\Domain\Queue\QueueEstimationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetEventQueuesAction extends BaseAction
{
    private const MIN_WINDOW_SECONDS = 60;

    private const MAX_WINDOW_SECONDS = 3600;

    public function __construct(
        private readonly QueueEstimationService $queueEstimationService,
    ) {}

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::ACCESS_LOGS_VIEW);

        $windowSeconds = min(
            self::MAX_WINDOW_SECONDS,
            max(self::MIN_WINDOW_SECONDS, (int) ($request->query('window_seconds') ?? 300))
        );

        $estimates = $this->queueEstimationService->estimateForEvent($eventId, $windowSeconds);

        return $this->jsonResponse([
            'data' => $estimates->map(static fn (QueueEstimateDTO $estimate): array => $estimate->toArray()
                + [
                    'projection' => null,
                    'suggests_misconfiguration' => $estimate->suggestsMisconfiguration(),
                ])->all(),
            'window_seconds' => $windowSeconds,
            'are_estimates' => true,
        ]);
    }
}
