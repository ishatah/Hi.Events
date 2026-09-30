<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\CommandCentre;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\CommandCentre\CommandCentreService;
use Illuminate\Http\JsonResponse;

/**
 * The event-day dashboard in one response.
 *
 * One request rather than a dozen, because a screen assembled from separate calls shows
 * figures from different instants and an operator comparing them has no way to know which.
 */
class GetCommandCentreSnapshotAction extends BaseAction
{
    public function __construct(
        private readonly CommandCentreService $commandCentreService,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::ACCESS_LOGS_VIEW);

        return $this->jsonResponse($this->commandCentreService->snapshot($eventId));
    }
}
