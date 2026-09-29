<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Accreditation;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Accreditation\AccreditationApprovalService;

class IssueAccreditationCredentialHandler
{
    public function __construct(
        private readonly AccreditationApprovalService $accreditationApprovalService,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $accreditationId, ?int $actorUserId): int
    {
        return $this->accreditationApprovalService->issueCredential($accreditationId, $actorUserId);
    }
}
