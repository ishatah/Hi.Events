<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Session;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Session\SessionAttendanceService;
use HiEvents\Services\Domain\Session\SessionRegistrationService;
use Illuminate\Http\JsonResponse;

class GetSessionStatsAction extends BaseAction
{
    public function __construct(
        private readonly SessionRegistrationService $sessionRegistrationService,
        private readonly SessionAttendanceService $sessionAttendanceService,
    ) {}

    public function __invoke(int $eventId, int $sessionId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->jsonResponse([
            'registered' => $this->sessionRegistrationService->registeredCount($sessionId),
            'waitlisted' => $this->sessionRegistrationService->waitlistCount($sessionId),
            'attended' => $this->sessionAttendanceService->attendedCount($sessionId),
            'no_shows' => $this->sessionAttendanceService->noShowCount($sessionId),
        ]);
    }
}
