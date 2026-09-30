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

class DrawRaffleAction extends BaseAction
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

            // The user who ran the draw is part of the audit record, not decoration: a
            // contested prize needs to show who pressed the button.
            $draw = $this->raffleService->draw(
                raffleId: $raffleId,
                drawnByUserId: $this->getAuthenticatedUser()->getId(),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['raffle_id' => $exception->getMessage()]);
        }

        return $this->jsonResponse([
            'draw' => $draw->toArray(),
            'winners' => $this->raffleService->winners($draw->drawId)->all(),
        ], statusCode: 201);
    }
}
