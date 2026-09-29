<?php

declare(strict_types=1);

namespace HiEvents\Console\Commands;

use HiEvents\Services\Domain\Permission\RoleSeedService;
use Illuminate\Console\Command;

class SeedPermissionRolesCommand extends Command
{
    protected $signature = 'permissions:seed-roles';

    protected $description = 'Create or reconcile the system permission roles';

    public function handle(RoleSeedService $roleSeedService): int
    {
        $seeded = $roleSeedService->seedSystemRoles();

        $this->table(
            [__('Role'), __('Permissions')],
            array_map(
                static fn (string $role, int $count): array => [$role, $count],
                array_keys($seeded),
                array_values($seeded)
            )
        );

        return self::SUCCESS;
    }
}
