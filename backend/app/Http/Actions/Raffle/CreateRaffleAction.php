<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Raffle;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Raffle\CreateRaffleRequest;
use HiEvents\Services\Domain\Raffle\RaffleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CreateRaffleAction extends BaseAction
{
    public function __construct(
        private readonly RaffleService $raffleService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(CreateRaffleRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::RAFFLE_MANAGE);

        try {
            $raffleId = $this->raffleService->create(
                eventId: $eventId,
                name: $request->validated('name'),
                windowStart: CarbonImmutable::parse($request->validated('eligibility_window_start')),
                windowEnd: CarbonImmutable::parse($request->validated('eligibility_window_end')),
                prizeDescription: $request->input('prize_description'),
                zoneId: $request->input('zone_id') !== null ? (int) $request->input('zone_id') : null,
                winnerCount: (int) ($request->input('winner_count') ?? 1),
                excludeStaff: (bool) ($request->input('exclude_staff') ?? true),
                excludeExhibitors: (bool) ($request->input('exclude_exhibitors') ?? true),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return $this->jsonResponse([
            'id' => $raffleId,
            'pool' => $this->raffleService->poolSummary($raffleId),
        ], statusCode: 201);
    }
}
