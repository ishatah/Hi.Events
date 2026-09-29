<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Authorization;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Services\Domain\Permission\PermissionResolutionService;

/**
 * Enforces a named capability, for an account or within one event.
 *
 * This runs alongside IsAuthorizedService rather than replacing it. That service answers
 * "does this entity belong to your account?", which stays necessary; this one answers "may
 * you do this?", which it never could. Migrating the 159 imperative call sites is the
 * high-risk step and is done incrementally, so both must hold for an action to proceed.
 *
 * @see docs/arzo-master-plan/09-permissions-and-roles.md
 */
readonly class PermissionGateService
{
    public function __construct(
        private PermissionResolutionService $permissionResolutionService,
    ) {}

    /**
     * @throws UnauthorizedException
     */
    public function authorizeAccountPermission(int $userId, int $accountId, Permission $permission): void
    {
        if (! $this->permissionResolutionService->accountHolds($userId, $accountId, $permission)) {
            throw new UnauthorizedException(
                __('You do not have permission to perform this action.')
            );
        }
    }

    /**
     * @throws UnauthorizedException
     */
    public function authorizeEventPermission(
        int $userId,
        int $accountId,
        int $eventId,
        Permission $permission
    ): void {
        if (! $this->permissionResolutionService->eventHolds($userId, $accountId, $eventId, $permission)) {
            throw new UnauthorizedException(
                __('You do not have permission to perform this action.')
            );
        }
    }
}
