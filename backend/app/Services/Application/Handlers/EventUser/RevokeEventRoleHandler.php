<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventUser;

use HiEvents\Services\Domain\Permission\EventRoleAssignmentService;

class RevokeEventRoleHandler
{
    public function __construct(
        private readonly EventRoleAssignmentService $eventRoleAssignmentService,
    ) {}

    public function handle(int $eventId, int $userId): void
    {
        $this->eventRoleAssignmentService->revoke($eventId, $userId);
    }
}
