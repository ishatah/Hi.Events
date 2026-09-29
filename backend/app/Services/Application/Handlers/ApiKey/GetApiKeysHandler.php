<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\ApiKey;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class GetApiKeysHandler
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function handle(int $accountId): Collection
    {
        return $this->databaseManager->table('api_keys')
            ->where('account_id', $accountId)
            ->orderByDesc('id')
            ->get();
    }
}
