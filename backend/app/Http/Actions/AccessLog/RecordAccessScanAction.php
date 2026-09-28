<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccessLog;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\AccessDirection;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\AccessLog\RecordAccessScanRequest;
use HiEvents\Services\Domain\Access\AccessScanService;
use Illuminate\Http\JsonResponse;

class RecordAccessScanAction extends BaseAction
{
    public function __construct(
        private readonly AccessScanService $accessScanService,
    ) {}

    public function __invoke(RecordAccessScanRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $direction = $request->validated('direction');

        $decision = $this->accessScanService->scan(
            eventId: $eventId,
            identifier: (string) $request->validated('identifier'),
            accessPointId: (int) $request->validated('access_point_id'),
            direction: $direction !== null ? AccessDirection::from($direction) : null,
            operatorUserId: $this->getAuthenticatedUser()->getId(),
            clientGeneratedId: $request->validated('client_generated_id'),
            occurredAt: $request->validated('occurred_at') !== null
                ? Carbon::parse((string) $request->validated('occurred_at'))
                : null,
            identifierType: (string) ($request->validated('identifier_type') ?? 'QR'),
        );

        // A denial is a valid, recorded outcome rather than a request error, so this
        // returns 200 with the verdict. The scanner needs the reason to display, and an
        // error status would make offline queues retry a decision that will not change.
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
