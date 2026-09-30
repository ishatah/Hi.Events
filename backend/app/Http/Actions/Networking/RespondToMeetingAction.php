<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Networking;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Networking\RespondToMeetingRequest;
use HiEvents\Services\Domain\Networking\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RespondToMeetingAction extends BaseAction
{
    public function __construct(
        private readonly MeetingService $meetingService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(RespondToMeetingRequest $request, int $eventId, int $meetingId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::NETWORKING_MANAGE);

        try {
            $this->meetingService->assertBelongsToEvent($meetingId, $eventId);

            $this->meetingService->respond(
                meetingId: $meetingId,
                personId: (int) $request->validated('person_id'),
                accept: (bool) $request->validated('accept'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['accept' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['recorded' => true]);
    }
}
