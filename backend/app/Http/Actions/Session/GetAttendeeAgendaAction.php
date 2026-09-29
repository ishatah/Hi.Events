<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Session;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Session\SessionConflictService;
use Illuminate\Http\JsonResponse;

class GetAttendeeAgendaAction extends BaseAction
{
    public function __construct(
        private readonly SessionConflictService $sessionConflictService,
    ) {}

    public function __invoke(int $eventId, int $attendeeId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->jsonResponse([
            'conflicts' => $this->sessionConflictService->forAttendee($eventId, $attendeeId)->all(),
        ]);
    }
}
