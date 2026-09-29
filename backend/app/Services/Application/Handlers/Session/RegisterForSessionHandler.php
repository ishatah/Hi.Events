<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Session;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Session\DTO\SessionRegistrationResultDTO;
use HiEvents\Services\Domain\Session\SessionRegistrationService;

class RegisterForSessionHandler
{
    public function __construct(
        private readonly SessionRegistrationService $sessionRegistrationService,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $sessionId, int $attendeeId): SessionRegistrationResultDTO
    {
        return $this->sessionRegistrationService->register($sessionId, $attendeeId);
    }
}
