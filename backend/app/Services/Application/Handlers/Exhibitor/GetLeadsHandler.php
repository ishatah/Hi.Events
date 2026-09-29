<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Exhibitor;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class GetLeadsHandler
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function handle(int $eventExhibitorId, ?string $rating = null, ?string $status = null): Collection
    {
        $query = $this->databaseManager->table('leads')
            ->where('event_exhibitor_id', $eventExhibitorId)
            ->whereNull('deleted_at')
            ->orderByDesc('last_captured_at');

        if ($rating !== null) {
            $query->where('rating', $rating);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }
}
