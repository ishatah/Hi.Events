<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Session;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\AccessDirection;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Session\SessionAttendanceService;

class RecordSessionAttendanceHandler
{
    public function __construct(
        private readonly SessionAttendanceService $sessionAttendanceService,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(
        int $sessionId,
        int $attendeeId,
        ?string $direction,
        ?int $accessPointId,
        ?int $recordedByUserId,
        ?string $clientGeneratedId,
        ?string $scannedAt,
    ): int {
        return $this->sessionAttendanceService->record(
            sessionId: $sessionId,
            attendeeId: $attendeeId,
            direction: $direction !== null ? AccessDirection::from($direction) : AccessDirection::ENTRY,
            accessPointId: $accessPointId,
            recordedByUserId: $recordedByUserId,
            clientGeneratedId: $clientGeneratedId,
            scannedAt: $scannedAt !== null ? Carbon::parse($scannedAt) : null,
        );
    }
}
