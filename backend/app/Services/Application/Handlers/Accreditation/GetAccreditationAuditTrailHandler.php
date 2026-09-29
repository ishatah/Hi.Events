<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Accreditation;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class GetAccreditationAuditTrailHandler
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function handle(int $accreditationId): Collection
    {
        return $this->databaseManager->table('accreditation_audit_logs')
            ->where('accreditation_id', $accreditationId)
            ->orderBy('occurred_at')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'from_status' => $row->from_status,
                'to_status' => $row->to_status,
                'reason' => $row->reason,
                'actor_user_id' => $row->actor_user_id !== null ? (int) $row->actor_user_id : null,
                'occurred_at' => $row->occurred_at,
            ]);
    }
}
