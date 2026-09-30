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
 * Who would be in the draw, without drawing.
 *
 * A pool of three when the organizer expected three hundred means the window or the zone is
 * wrong, and that is far better found before a winner has been announced.
 */
class GetRafflePoolAction extends BaseAction
{
    public function __construct(
        private readonly RaffleService $raffleService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(int $eventId, int $raffleId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::RAFFLE_MANAGE);

        try {
            $this->raffleService->assertBelongsToEvent($raffleId, $eventId);
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['raffle_id' => $exception->getMessage()]);
        }

        return $this->jsonResponse($this->raffleService->poolSummary($raffleId));
    }
}
