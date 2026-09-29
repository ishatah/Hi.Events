<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Session;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Session\CancelSessionRegistrationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CancelSessionRegistrationAction extends BaseAction
{
    public function __construct(
        private readonly CancelSessionRegistrationHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $sessionId, int $attendeeId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $promotedAttendeeId = $this->handler->handle($sessionId, $attendeeId);
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['attendee_id' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['promoted_attendee_id' => $promotedAttendeeId]);
    }
}
