<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventUser;

use HiEvents\DomainObjects\EventUserDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Permission\EventRoleAssignmentService;

class GrantEventRoleHandler
{
    public function __construct(
        private readonly EventRoleAssignmentService $eventRoleAssignmentService,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(
        int $eventId,
        int $userId,
        string $roleName,
        int $accountId,
        ?int $grantedByUserId,
        ?string $expiresAt,
    ): EventUserDomainObject {
        return $this->eventRoleAssignmentService->grant(
            eventId: $eventId,
            userId: $userId,
            roleName: $roleName,
            accountId: $accountId,
            grantedByUserId: $grantedByUserId,
            expiresAt: $expiresAt,
        );
    }
}
