<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Analytics;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Analytics\AttendanceAnalyticsService;
use Illuminate\Http\JsonResponse;

class GetDemographicsAction extends BaseAction
{
    public function __construct(
        private readonly AttendanceAnalyticsService $analytics,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::REPORT_VIEW);

        return $this->jsonResponse($this->analytics->demographics($eventId));
    }
}
