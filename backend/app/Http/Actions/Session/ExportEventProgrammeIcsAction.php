<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Session;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Session\SessionIcsExportService;
use Illuminate\Http\Response as LaravelResponse;

class ExportEventProgrammeIcsAction extends BaseAction
{
    public function __construct(
        private readonly SessionIcsExportService $sessionIcsExportService,
    ) {}

    public function __invoke(int $eventId): LaravelResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->calendarResponse(
            $this->sessionIcsExportService->forEventProgramme($eventId),
            'programme.ics'
        );
    }
}
