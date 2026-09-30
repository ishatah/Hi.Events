<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Networking;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Networking\RequestMeetingRequest;
use HiEvents\Services\Domain\Networking\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Books a meeting on behalf of a delegate.
 *
 * Organizer-side: an event team arranging a buyer programme, or a concierge desk booking for
 * somebody. The attendee-facing equivalent needs an attendee identity, which plan 30 leaves as
 * an open product question between accounts and magic links.
 */
class RequestMeetingAction extends BaseAction
{
    public function __construct(
        private readonly MeetingService $meetingService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(RequestMeetingRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::NETWORKING_MANAGE);

        try {
            $result = $this->meetingService->request(
                eventId: $eventId,
                requesterPersonId: (int) $request->validated('requester_person_id'),
                inviteePersonIds: $request->validated('invitee_person_ids'),
                startsAt: CarbonImmutable::parse($request->validated('starts_at')),
                endsAt: CarbonImmutable::parse($request->validated('ends_at')),
                eventExhibitorId: $request->input('event_exhibitor_id') !== null
                    ? (int) $request->input('event_exhibitor_id')
                    : null,
                roomId: $request->input('room_id') !== null ? (int) $request->input('room_id') : null,
                locationLabel: $request->input('location_label'),
                note: $request->input('note'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['starts_at' => $exception->getMessage()]);
        }

        return $this->jsonResponse($result, statusCode: 201);
    }
}
