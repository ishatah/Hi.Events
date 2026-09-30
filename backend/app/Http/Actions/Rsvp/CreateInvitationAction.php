<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Rsvp;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Rsvp\CreateInvitationRequest;
use HiEvents\Services\Domain\Rsvp\RsvpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CreateInvitationAction extends BaseAction
{
    public function __construct(
        private readonly RsvpService $rsvpService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(CreateInvitationRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::GUEST_LIST_MANAGE);

        $expiresAt = $request->input('expires_at');

        try {
            $invited = $this->rsvpService->invite(
                eventId: $eventId,
                firstName: $request->validated('first_name'),
                email: $request->validated('email'),
                lastName: $request->input('last_name'),
                phone: $request->input('phone'),
                maxPartySize: (int) ($request->input('max_party_size') ?? 1),
                expiresAt: $expiresAt !== null ? Carbon::parse((string) $expiresAt) : null,
                personId: $request->input('person_id') !== null ? (int) $request->input('person_id') : null,
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['email' => $exception->getMessage()]);
        }

        return $this->jsonResponse($invited, statusCode: 201);
    }
}
