<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\ApiKey;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\ApiKey\ApiKeyIssuanceService;

class CreateApiKeyHandler
{
    public function __construct(
        private readonly ApiKeyIssuanceService $apiKeyIssuanceService,
    ) {}

    /**
     * @param  array<int, string>  $scopes
     * @param  array<int, string>|null  $allowedIps
     * @return array{id: int, plaintext: string, prefix: string}
     *
     * @throws ResourceConflictException
     */
    public function handle(
        int $accountId,
        int $creatorUserId,
        string $name,
        array $scopes,
        ?int $organizerId,
        ?int $eventId,
        ?int $rateLimitPerMinute,
        ?array $allowedIps,
        ?string $expiresAt,
    ): array {
        return $this->apiKeyIssuanceService->issue(
            accountId: $accountId,
            creatorUserId: $creatorUserId,
            name: $name,
            scopes: $scopes,
            organizerId: $organizerId,
            eventId: $eventId,
            rateLimitPerMinute: $rateLimitPerMinute,
            allowedIps: $allowedIps,
            expiresAt: $expiresAt,
        );
    }
}
