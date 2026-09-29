<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccessLog;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\AccessDirection;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\AccessLog\SimulateAccessScanRequest;
use HiEvents\Services\Domain\Access\AccessScanService;
use Illuminate\Http\JsonResponse;

class SimulateAccessScanAction extends BaseAction
{
    public function __construct(
        private readonly AccessScanService $accessScanService,
    ) {}

    public function __invoke(SimulateAccessScanRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $direction = $request->validated('direction');
        $occurredAt = $request->validated('occurred_at');

        $decision = $this->accessScanService->simulate(
            eventId: $eventId,
            identifier: (string) $request->validated('identifier'),
            accessPointId: (int) $request->validated('access_point_id'),
            direction: $direction !== null ? AccessDirection::from($direction) : null,
            occurredAt: $occurredAt !== null ? Carbon::parse((string) $occurredAt) : null,
        );

        return $this->jsonResponse([
            'result' => $decision->result->value,
            'granted' => $decision->isGranted(),
            'credential_id' => $decision->credentialId,
            'matched_grant_id' => $decision->matchedGrantId,
            'matched_rule_id' => $decision->matchedRuleId,
            'reason' => $decision->reason,
        ]);
    }
}
