<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Accreditation;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Accreditation\AccreditationApprovalService;

class SubmitAccreditationHandler
{
    public function __construct(
        private readonly AccreditationApprovalService $accreditationApprovalService,
    ) {}

    /**
     * @param  array<int, int>|null  $requestedZoneIds
     * @param  array<string, mixed>|null  $formData
     *
     * @throws ResourceConflictException
     */
    public function handle(
        int $eventId,
        int $personId,
        int $accreditationTypeId,
        ?array $requestedZoneIds,
        ?array $formData,
        ?int $actorUserId,
    ): int {
        return $this->accreditationApprovalService->submit(
            eventId: $eventId,
            personId: $personId,
            accreditationTypeId: $accreditationTypeId,
            requestedZoneIds: $requestedZoneIds,
            formData: $formData,
            actorUserId: $actorUserId,
        );
    }
}
