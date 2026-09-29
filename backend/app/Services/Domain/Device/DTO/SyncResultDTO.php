<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Device\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SyncResultDTO extends BaseDataObject
{
    /**
     * @param  array<int, array<string, mixed>>  $rejectedLogs
     * @param  array<int, array<string, mixed>>  $credentialsDelta
     * @param  array<int, array<string, mixed>>  $grantsDelta
     * @param  array<int, string>  $denyList
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public readonly int $acceptedLogs,
        public readonly int $duplicateLogs,
        public readonly array $rejectedLogs,
        public readonly array $credentialsDelta,
        public readonly array $grantsDelta,
        public readonly array $denyList,
        public readonly array $config,
        public readonly string $newCursor,
        public readonly bool $hasMore,
    ) {}
}
