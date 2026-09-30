<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Rsvp;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Rsvp\InvitationResource;
use HiEvents\Services\Domain\Rsvp\RsvpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetGuestListAction extends BaseAction
{
    public function __construct(
        private readonly RsvpService $rsvpService,
    ) {}

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::GUEST_LIST_MANAGE);

        $status = $request->query('status') !== null ? (string) $request->query('status') : null;

        return $this->jsonResponse([
            'data' => $this->rsvpService->guestList($eventId, $status)
                ->map(static fn (object $row): array => (new InvitationResource($row))->toArray(request()))
                ->values()
                ->all(),
            'summary' => $this->rsvpService->summary($eventId),
        ]);
    }
}
