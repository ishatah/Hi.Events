<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Permission;

use HiEvents\DomainObjects\Enums\SystemRole;
use HiEvents\DomainObjects\EventUserDomainObject;
use HiEvents\DomainObjects\Generated\EventUserDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\EventUserRepositoryInterface;
use HiEvents\Repository\Interfaces\PermissionRoleRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Grants and revokes a user's role on a single event.
 *
 * @see docs/arzo-master-plan/09-permissions-and-roles.md
 */
class EventRoleAssignmentService
{
    public function __construct(
        private readonly EventUserRepositoryInterface $eventUserRepository,
        private readonly PermissionRoleRepositoryInterface $permissionRoleRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function grant(
        int $eventId,
        int $userId,
        string $roleName,
        int $accountId,
        ?int $grantedByUserId = null,
        ?string $expiresAt = null,
    ): EventUserDomainObject {
        $role = SystemRole::tryFrom($roleName);

        if ($role === null || ! in_array($role, SystemRole::eventScopedRoles(), true)) {
            throw new ResourceConflictException(
                __('That role cannot be granted on a single event.')
            );
        }

        if (! $this->userBelongsToAccount($userId, $accountId)) {
            throw new ResourceConflictException(
                __('That user is not a member of this account.')
            );
        }

        $roleId = $this->permissionRoleRepository->findFirstWhere([
            'name' => $role->value,
            'account_id' => null,
        ])?->getId();

        if ($roleId === null) {
            throw new ResourceConflictException(
                __('The system roles have not been seeded.')
            );
        }

        $existing = $this->eventUserRepository->findFirstWhere([
            EventUserDomainObjectAbstract::EVENT_ID => $eventId,
            EventUserDomainObjectAbstract::USER_ID => $userId,
        ]);

        if ($existing !== null) {
            $this->eventUserRepository->updateWhere(
                attributes: [
                    EventUserDomainObjectAbstract::PERMISSION_ROLE_ID => $roleId,
                    EventUserDomainObjectAbstract::GRANTED_BY => $grantedByUserId,
                    EventUserDomainObjectAbstract::GRANTED_AT => now()->toDateTimeString(),
                    EventUserDomainObjectAbstract::EXPIRES_AT => $expiresAt,
                ],
                where: [EventUserDomainObjectAbstract::ID => $existing->getId()],
            );

            return $this->eventUserRepository->findById($existing->getId());
        }

        return $this->eventUserRepository->create([
            EventUserDomainObjectAbstract::SHORT_ID => 'eu_'.Str::lower(Str::random(20)),
            EventUserDomainObjectAbstract::EVENT_ID => $eventId,
            EventUserDomainObjectAbstract::USER_ID => $userId,
            EventUserDomainObjectAbstract::PERMISSION_ROLE_ID => $roleId,
            EventUserDomainObjectAbstract::GRANTED_BY => $grantedByUserId,
            EventUserDomainObjectAbstract::GRANTED_AT => now()->toDateTimeString(),
            EventUserDomainObjectAbstract::EXPIRES_AT => $expiresAt,
        ]);
    }

    public function revoke(int $eventId, int $userId): void
    {
        $this->eventUserRepository->deleteWhere([
            EventUserDomainObjectAbstract::EVENT_ID => $eventId,
            EventUserDomainObjectAbstract::USER_ID => $userId,
        ]);
    }

    private function userBelongsToAccount(int $userId, int $accountId): bool
    {
        return $this->databaseManager->table('account_users')
            ->where('user_id', $userId)
            ->where('account_id', $accountId)
            ->exists();
    }
}
