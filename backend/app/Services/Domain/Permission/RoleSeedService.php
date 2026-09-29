<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Permission;

use HiEvents\DomainObjects\Enums\SystemRole;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Creates the system roles and their permission sets.
 *
 * Idempotent, because it runs on deploy and after any change to a role's permission list.
 * A role's membership is reconciled rather than appended to, so removing a permission from
 * a role definition actually revokes it.
 *
 * @see docs/arzo-master-plan/09-permissions-and-roles.md
 */
class RoleSeedService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @return array<string, int> role name => permission count
     */
    public function seedSystemRoles(): array
    {
        $permissionIds = $this->databaseManager->table('permissions')
            ->pluck('id', 'name')
            ->all();

        $seeded = [];

        foreach (SystemRole::cases() as $role) {
            $roleId = $this->upsertRole($role);

            $wanted = [];

            foreach ($role->permissions() as $permission) {
                if (isset($permissionIds[$permission->value])) {
                    $wanted[] = $permissionIds[$permission->value];
                }
            }

            $this->reconcilePermissions($roleId, $wanted);

            $seeded[$role->value] = count($wanted);
        }

        return $seeded;
    }

    private function upsertRole(SystemRole $role): int
    {
        $existing = $this->databaseManager->table('permission_roles')
            ->where('name', $role->value)
            ->whereNull('account_id')
            ->whereNull('deleted_at')
            ->first();

        if ($existing !== null) {
            $this->databaseManager->table('permission_roles')
                ->where('id', $existing->id)
                ->update([
                    'description' => $role->description(),
                    'is_system' => true,
                    'updated_at' => now(),
                ]);

            return (int) $existing->id;
        }

        return (int) $this->databaseManager->table('permission_roles')->insertGetId([
            'short_id' => 'pr_'.Str::lower(Str::random(20)),
            'account_id' => null,
            'name' => $role->value,
            'description' => $role->description(),
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, int>  $wantedPermissionIds
     */
    private function reconcilePermissions(int $roleId, array $wantedPermissionIds): void
    {
        $current = $this->databaseManager->table('permission_role_permissions')
            ->where('permission_role_id', $roleId)
            ->pluck('permission_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $toAdd = array_diff($wantedPermissionIds, $current);
        $toRemove = array_diff($current, $wantedPermissionIds);

        if ($toRemove !== []) {
            $this->databaseManager->table('permission_role_permissions')
                ->where('permission_role_id', $roleId)
                ->whereIn('permission_id', $toRemove)
                ->delete();
        }

        if ($toAdd === []) {
            return;
        }

        $this->databaseManager->table('permission_role_permissions')->insert(
            array_map(static fn (int $permissionId): array => [
                'permission_role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ], array_values($toAdd))
        );
    }
}
