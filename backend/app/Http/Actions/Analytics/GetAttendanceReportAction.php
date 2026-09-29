<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Analytics;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Analytics\AttendanceAnalyticsService;
use Illuminate\Http\JsonResponse;

class GetAttendanceReportAction extends BaseAction
{
    public function __construct(
        private readonly AttendanceAnalyticsService $analytics,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::REPORT_VIEW);

        return $this->jsonResponse([
            'attendance' => $this->analytics->eventAttendance($eventId),
            'arrival_curve' => $this->analytics->arrivalCurve($eventId),
            'peak_arrival_hour' => $this->analytics->peakArrivalHour($eventId),
        ]);
    }
}
