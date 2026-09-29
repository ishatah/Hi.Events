<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Exhibitor;

use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;

class UpdateLeadHandler
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @param  array<string, mixed>|null  $qualification
     *
     * @throws ResourceConflictException
     */
    public function handle(
        int $leadId,
        int $eventExhibitorId,
        ?string $rating,
        ?string $status,
        ?string $notes,
        ?array $qualification,
    ): object {
        $lead = $this->databaseManager->table('leads')
            ->where('id', $leadId)
            ->where('event_exhibitor_id', $eventExhibitorId)
            ->whereNull('deleted_at')
            ->first();

        if ($lead === null) {
            throw new ResourceConflictException(__('That lead could not be found.'));
        }

        // Only what was sent is written. A rating set at the booth must survive a later note
        // being added without the rating being resent.
        $attributes = array_filter([
            'rating' => $rating,
            'status' => $status,
            'notes' => $notes,
            'qualification' => $qualification !== null ? json_encode($qualification) : null,
        ], static fn ($value): bool => $value !== null);

        if ($attributes !== []) {
            $this->databaseManager->table('leads')
                ->where('id', $leadId)
                ->update($attributes + ['updated_at' => now()]);
        }

        return $this->databaseManager->table('leads')->where('id', $leadId)->first();
    }
}
