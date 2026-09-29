<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Operations;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\IncidentSeverity;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Operations\ReportIncidentRequest;
use HiEvents\Services\Domain\Operations\IncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ReportIncidentAction extends BaseAction
{
    public function __construct(
        private readonly IncidentService $incidentService,
    ) {}

    public function __invoke(ReportIncidentRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::INCIDENT_MANAGE);

        try {
            $incidentId = $this->incidentService->report(
                eventId: $eventId,
                title: (string) $request->validated('title'),
                category: (string) $request->validated('category'),
                severity: IncidentSeverity::from((string) $request->validated('severity')),
                reportedByUserId: $this->getAuthenticatedUser()->getId(),
                description: $request->validated('description'),
                zoneId: $request->validated('zone_id') !== null ? (int) $request->validated('zone_id') : null,
                occurredAt: $request->validated('occurred_at') !== null
                    ? Carbon::parse((string) $request->validated('occurred_at'))
                    : null,
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['title' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['id' => $incidentId]);
    }
}
