<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Raffle;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Raffle\RaffleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Re-runs a recorded draw over the same pool.
 *
 * This is what makes the audit trail worth keeping: a challenged result is settled by
 * reproducing it rather than by asserting the code is fair.
 */
class VerifyRaffleDrawAction extends BaseAction
{
    public function __construct(
        private readonly RaffleService $raffleService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(int $eventId, int $raffleId, int $drawId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::RAFFLE_MANAGE);

        try {
            $this->raffleService->assertBelongsToEvent($raffleId, $eventId);

            return $this->jsonResponse($this->raffleService->verify($drawId));
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['draw_id' => $exception->getMessage()]);
        }
    }
}
