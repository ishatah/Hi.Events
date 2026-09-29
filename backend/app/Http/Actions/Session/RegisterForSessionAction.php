<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Session;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Session\RegisterForSessionRequest;
use HiEvents\Resources\Session\SessionRegistrationResource;
use HiEvents\Services\Application\Handlers\Session\RegisterForSessionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RegisterForSessionAction extends BaseAction
{
    public function __construct(
        private readonly RegisterForSessionHandler $handler,
    ) {}

    public function __invoke(RegisterForSessionRequest $request, int $eventId, int $sessionId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $result = $this->handler->handle($sessionId, (int) $request->validated('attendee_id'));
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['attendee_id' => $exception->getMessage()]);
        }

        if ($result->waitlisted) {
            return $this->jsonResponse([
                'waitlisted' => true,
                'waitlist_position' => $result->waitlistPosition,
            ]);
        }

        return $this->resourceResponse(SessionRegistrationResource::class, $result->registration);
    }
}
