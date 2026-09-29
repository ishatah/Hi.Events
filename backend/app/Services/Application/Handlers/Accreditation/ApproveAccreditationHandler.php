<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Accreditation;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Accreditation\AccreditationApprovalService;

class ApproveAccreditationHandler
{
    public function __construct(
        private readonly AccreditationApprovalService $accreditationApprovalService,
    ) {}

    /**
     * @param  array<int, int>|null  $approvedZoneIds
     *
     * @throws ResourceConflictException
     */
    public function handle(
        int $accreditationId,
        ?int $actorUserId,
        ?array $approvedZoneIds,
        ?string $notes,
    ): void {
        $this->accreditationApprovalService->approve(
            accreditationId: $accreditationId,
            actorUserId: $actorUserId,
            approvedZoneIds: $approvedZoneIds,
            notes: $notes,
        );
    }
}
