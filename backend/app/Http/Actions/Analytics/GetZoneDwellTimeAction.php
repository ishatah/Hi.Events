<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Analytics;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Analytics\AttendanceAnalyticsService;
use Illuminate\Http\JsonResponse;

class GetZoneDwellTimeAction extends BaseAction
{
    public function __construct(
        private readonly AttendanceAnalyticsService $analytics,
    ) {}

    public function __invoke(int $eventId, int $zoneId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::REPORT_VIEW);

        $dwell = $this->analytics->zoneDwellTime($zoneId, $eventId);

        // measurable=false is a real answer, not an error. A zone with no exit point cannot
        // report dwell, and saying so is the point.
        return $this->jsonResponse([
            'measurable' => $dwell->measurable,
            'sample_size' => $dwell->sampleSize,
            'average_seconds' => $dwell->averageSeconds,
            'median_seconds' => $dwell->medianSeconds,
            'reason' => $dwell->reason,
        ]);
    }
}
