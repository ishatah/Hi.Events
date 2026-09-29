<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Session;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Session\SessionIcsExportService;
use Illuminate\Http\Response as LaravelResponse;

class ExportSessionIcsAction extends BaseAction
{
    public function __construct(
        private readonly SessionIcsExportService $sessionIcsExportService,
    ) {}

    public function __invoke(int $eventId, int $sessionId): LaravelResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $calendar = $this->sessionIcsExportService->forSession($sessionId);

        if ($calendar === null) {
            return $this->notFoundResponse();
        }

        return $this->calendarResponse($calendar, 'session.ics');
    }
}
