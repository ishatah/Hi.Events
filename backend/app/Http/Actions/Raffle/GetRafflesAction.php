<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Raffle;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Raffle\RaffleService;
use Illuminate\Http\JsonResponse;

class GetRafflesAction extends BaseAction
{
    public function __construct(
        private readonly RaffleService $raffleService,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::RAFFLE_MANAGE);

        return $this->jsonResponse(['data' => $this->raffleService->listForEvent($eventId)->all()]);
    }
}
