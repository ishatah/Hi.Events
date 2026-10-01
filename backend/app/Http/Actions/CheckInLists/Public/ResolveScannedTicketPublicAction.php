<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\CheckInLists\Public;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\CheckInList\ResolveScannedTicketRequest;
use HiEvents\Services\Application\Handlers\CheckInList\Public\GetCheckInListAttendeePublicHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Turns a scanned ticket QR into the attendee's check-in identifier.
 *
 * The QR payload travels in the request body and never appears in a response. That asymmetry
 * is the whole point: a scanner already holds the code it just read, so sending it inward
 * reveals nothing, while a list or detail endpoint that sent codes outward let anyone with the
 * link mint every ticket.
 *
 * POST rather than GET so the code does not land in access logs, proxy caches or browser
 * history.
 *
 * @see docs/arzo-master-plan/136-master-backlog.md ARZ-334
 */
class ResolveScannedTicketPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetCheckInListAttendeePublicHandler $handler,
    ) {}

    public function __invoke(ResolveScannedTicketRequest $request, string $checkInListShortId): JsonResponse
    {
        try {
            $attendee = $this->handler->handleByPublicId(
                shortId: $checkInListShortId,
                ticketCode: $request->validated('ticket_code'),
            );
        } catch (ResourceNotFoundException) {
            // Deliberately the same shape for an unknown code and one belonging to another
            // list: a scanner has no use for the difference, and telling them apart turns
            // this into an oracle for guessing ticket codes.
            return $this->errorResponse(__('Ticket not found'), 404);
        }

        return $this->jsonResponse([
            'short_id' => $attendee->getShortId(),
            'first_name' => $attendee->getFirstName(),
            'last_name' => $attendee->getLastName(),
            'status' => $attendee->getStatus(),
        ]);
    }
}
