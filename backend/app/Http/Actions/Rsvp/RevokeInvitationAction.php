<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Rsvp;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Rsvp\RsvpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Validation\ValidationException;

class RevokeInvitationAction extends BaseAction
{
    public function __construct(
        private readonly RsvpService $rsvpService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(int $eventId, int $invitationId): LaravelResponse|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::GUEST_LIST_MANAGE);

        try {
            $this->rsvpService->revoke($invitationId, $eventId);
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['invitation_id' => $exception->getMessage()]);
        }

        return $this->deletedResponse();
    }
}
