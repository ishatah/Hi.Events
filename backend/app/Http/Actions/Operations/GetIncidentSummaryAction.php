<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Operations;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Operations\IncidentService;
use Illuminate\Http\JsonResponse;

class GetIncidentSummaryAction extends BaseAction
{
    public function __construct(
        private readonly IncidentService $incidentService,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::INCIDENT_MANAGE);

        return $this->jsonResponse([
            'summary' => $this->incidentService->summary($eventId),
            // The list a control room escalates from, rather than a count of everything open.
            'breaching_acknowledgement' => $this->incidentService->breachingAcknowledgement($eventId),
        ]);
    }
}
