<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Permission;

use HiEvents\DomainObjects\Enums\Permission;
use Illuminate\Database\DatabaseManager;

/**
 * Answers what a user may do, for an account and optionally within one event.
 *
 * Resolved per request rather than carried in the JWT. In-token permissions are faster but
 * go stale, and the existing model already suffers from that: a role change does not take
 * effect until the token refreshes. Correctness wins here because the answer gates access
 * to doors and money.
 *
 * @see docs/arzo-master-plan/09-permissions-and-roles.md
 */
class PermissionResolutionService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * Permissions a user holds across the account, ignoring per-event grants.
     *
     * @return array<int, string>
     */
    public function accountPermissions(int $userId, int $accountId): array
    {
        $roleName = $this->databaseManager->table('account_users')
            ->where('user_id', $userId)
            ->where('account_id', $accountId)
            ->value('role');

        if ($roleName === null) {
            return [];
        }

        return $this->permissionsForRoleName((string) $roleName);
    }

    /**
     * Permissions a user holds for one event.
     *
     * An account-level role grants a baseline across every event in the account. An
     * `event_users` row grants its role's permissions for that event in addition, which is
     * what lets a check-in operator on one event hold nothing on another. An expired grant
     * contributes nothing, so temporary staff lose access on time rather than when somebody
     * remembers to revoke it.
     *
     * @return array<int, string>
     */
    public function eventPermissions(int $userId, int $accountId, int $eventId): array
    {
        $permissions = $this->accountPermissions($userId, $accountId);

        $eventRoleNames = $this->databaseManager->table('event_users')
            ->join('permission_roles', 'permission_roles.id', '=', 'event_users.permission_role_id')
            ->where('event_users.event_id', $eventId)
            ->where('event_users.user_id', $userId)
            ->whereNull('event_users.deleted_at')
            ->whereNull('permission_roles.deleted_at')
            ->where(function ($query) {
                $query->whereNull('event_users.expires_at')
                    ->orWhere('event_users.expires_at', '>', now());
            })
            ->pluck('permission_roles.name')
            ->all();

        foreach ($eventRoleNames as $roleName) {
            $permissions = array_merge($permissions, $this->permissionsForRoleName((string) $roleName));
        }

        return array_values(array_unique($permissions));
    }

    public function accountHolds(int $userId, int $accountId, Permission $permission): bool
    {
        return in_array($permission->value, $this->accountPermissions($userId, $accountId), true);
    }

    public function eventHolds(int $userId, int $accountId, int $eventId, Permission $permission): bool
    {
        return in_array($permission->value, $this->eventPermissions($userId, $accountId, $eventId), true);
    }

    /**
     * @return array<int, string>
     */
    private function permissionsForRoleName(string $roleName): array
    {
        return $this->databaseManager->table('permission_roles')
            ->join(
                'permission_role_permissions',
                'permission_role_permissions.permission_role_id',
                '=',
                'permission_roles.id'
            )
            ->join('permissions', 'permissions.id', '=', 'permission_role_permissions.permission_id')
            ->where('permission_roles.name', $roleName)
            ->whereNull('permission_roles.account_id')
            ->whereNull('permission_roles.deleted_at')
            ->pluck('permissions.name')
            ->map(static fn ($name): string => (string) $name)
            ->all();
    }
}
