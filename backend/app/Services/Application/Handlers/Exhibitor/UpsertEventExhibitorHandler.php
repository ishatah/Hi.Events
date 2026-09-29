<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Exhibitor;

use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class UpsertEventExhibitorHandler
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(
        int $eventId,
        int $accountId,
        int $companyId,
        string $status,
        ?string $packageName,
        ?int $staffPassQuota,
        ?float $contractValue,
        ?string $currency,
    ): object {
        $companyBelongs = $this->databaseManager->table('companies')
            ->where('id', $companyId)
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $companyBelongs) {
            throw new ResourceConflictException(__('That company does not belong to this account.'));
        }

        $existing = $this->databaseManager->table('event_exhibitors')
            ->where('event_id', $eventId)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        $attributes = [
            'status' => $status,
            'package_name' => $packageName,
            'staff_pass_quota' => $staffPassQuota,
            'contract_value' => $contractValue,
            'currency' => $currency,
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            $this->databaseManager->table('event_exhibitors')
                ->where('id', $existing->id)
                ->update($attributes);

            return $this->find((int) $existing->id);
        }

        try {
            $id = (int) $this->databaseManager->table('event_exhibitors')->insertGetId(
                $attributes + [
                    'short_id' => 'ee_'.Str::lower(Str::random(20)),
                    'event_id' => $eventId,
                    'company_id' => $companyId,
                    'created_at' => now(),
                ]
            );
        } catch (UniqueConstraintViolationException) {
            throw new ResourceConflictException(
                __('That company is already registered for this event.')
            );
        }

        return $this->find($id);
    }

    private function find(int $id): object
    {
        return $this->databaseManager->table('event_exhibitors')
            ->join('companies', 'companies.id', '=', 'event_exhibitors.company_id')
            ->where('event_exhibitors.id', $id)
            ->select(['event_exhibitors.*', 'companies.name as company_name'])
            ->first();
    }
}
