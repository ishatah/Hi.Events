<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Session;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Session\RecordSessionAttendanceRequest;
use HiEvents\Services\Application\Handlers\Session\RecordSessionAttendanceHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RecordSessionAttendanceAction extends BaseAction
{
    public function __construct(
        private readonly RecordSessionAttendanceHandler $handler,
    ) {}

    public function __invoke(
        RecordSessionAttendanceRequest $request,
        int $eventId,
        int $sessionId
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::ATTENDEE_CHECKIN);

        try {
            $attendanceId = $this->handler->handle(
                sessionId: $sessionId,
                attendeeId: (int) $request->validated('attendee_id'),
                direction: $request->validated('direction'),
                accessPointId: $request->validated('access_point_id') !== null
                    ? (int) $request->validated('access_point_id')
                    : null,
                recordedByUserId: $this->getAuthenticatedUser()->getId(),
                clientGeneratedId: $request->validated('client_generated_id'),
                scannedAt: $request->validated('scanned_at'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['attendee_id' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['id' => $attendanceId]);
    }
}
