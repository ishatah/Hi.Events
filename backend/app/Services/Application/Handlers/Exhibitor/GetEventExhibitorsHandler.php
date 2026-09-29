<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Exhibitor;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class GetEventExhibitorsHandler
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function handle(int $eventId): Collection
    {
        return $this->databaseManager->table('event_exhibitors')
            ->join('companies', 'companies.id', '=', 'event_exhibitors.company_id')
            ->where('event_exhibitors.event_id', $eventId)
            ->whereNull('event_exhibitors.deleted_at')
            ->orderBy('companies.name')
            ->select(['event_exhibitors.*', 'companies.name as company_name'])
            ->get();
    }
}
