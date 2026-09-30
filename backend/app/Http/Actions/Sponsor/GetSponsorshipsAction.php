<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Sponsor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Sponsor\SponsorshipResource;
use HiEvents\Services\Domain\Sponsor\SponsorshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetSponsorshipsAction extends BaseAction
{
    public function __construct(
        private readonly SponsorshipService $sponsorshipService,
    ) {}

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::SPONSOR_MANAGE);

        $status = $request->query('status') !== null ? (string) $request->query('status') : null;

        return $this->jsonResponse([
            'data' => $this->sponsorshipService->listForEvent($eventId, $status)
                ->map(static fn (object $row): array => (new SponsorshipResource($row))->toArray(request()))
                ->values()
                ->all(),
            'packages' => $this->sponsorshipService->listPackages($eventId)->all(),
        ]);
    }
}
