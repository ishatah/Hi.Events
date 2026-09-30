<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Networking;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Networking\NetworkingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The directory as one person sees it, with their blocks applied.
 *
 * Scoped to a viewer rather than listing everybody, because a block is only meaningful from
 * somebody's point of view, and an unscoped listing would hand staff a view the attendees
 * themselves do not have.
 */
class GetNetworkingDirectoryAction extends BaseAction
{
    public function __construct(
        private readonly NetworkingService $networkingService,
    ) {}

    public function __invoke(Request $request, int $eventId, int $personId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::NETWORKING_MANAGE);

        $search = $request->query('search') !== null ? (string) $request->query('search') : null;

        return $this->jsonResponse([
            'data' => $this->networkingService->directory($eventId, $personId, $search)->all(),
            'connections' => $this->networkingService->connectionsFor($eventId, $personId)->all(),
        ]);
    }
}
