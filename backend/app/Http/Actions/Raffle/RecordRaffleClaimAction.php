<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Raffle;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Raffle\RecordRaffleClaimRequest;
use HiEvents\Services\Domain\Raffle\RaffleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RecordRaffleClaimAction extends BaseAction
{
    public function __construct(
        private readonly RaffleService $raffleService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(
        RecordRaffleClaimRequest $request,
        int $eventId,
        int $raffleId,
        int $winnerId
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::RAFFLE_MANAGE);

        try {
            $this->raffleService->assertBelongsToEvent($raffleId, $eventId);
            $this->raffleService->recordClaim($winnerId, (bool) $request->validated('claimed'));
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['winner_id' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['recorded' => true]);
    }
}
