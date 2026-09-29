<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\EventUser;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\EventUser\GrantEventRoleRequest;
use HiEvents\Resources\EventUser\EventUserResource;
use HiEvents\Services\Application\Handlers\EventUser\GrantEventRoleHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class GrantEventRoleAction extends BaseAction
{
    public function __construct(
        private readonly GrantEventRoleHandler $handler,
    ) {}

    public function __invoke(GrantEventRoleRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::STAFF_MANAGE);

        try {
            $eventUser = $this->handler->handle(
                eventId: $eventId,
                userId: (int) $request->validated('user_id'),
                roleName: (string) $request->validated('role'),
                accountId: $this->getAuthenticatedAccountId(),
                grantedByUserId: $this->getAuthenticatedUser()->getId(),
                expiresAt: $request->validated('expires_at'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['role' => $exception->getMessage()]);
        }

        return $this->resourceResponse(EventUserResource::class, $eventUser);
    }
}
