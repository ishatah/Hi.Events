<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\ApiKey;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\ApiKey\ApiKeyIssuanceService;

class RevokeApiKeyHandler
{
    public function __construct(
        private readonly ApiKeyIssuanceService $apiKeyIssuanceService,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $apiKeyId, int $accountId, ?int $revokedByUserId): void
    {
        $this->apiKeyIssuanceService->revoke($apiKeyId, $accountId, $revokedByUserId);
    }
}
