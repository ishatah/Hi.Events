<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Session;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Session\SessionRegistrationService;

class CancelSessionRegistrationHandler
{
    public function __construct(
        private readonly SessionRegistrationService $sessionRegistrationService,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $sessionId, int $attendeeId): ?int
    {
        return $this->sessionRegistrationService->cancel($sessionId, $attendeeId);
    }
}
