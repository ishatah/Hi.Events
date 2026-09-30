<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Sponsor;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Sponsor\PublicSponsorResource;
use HiEvents\Services\Domain\Sponsor\SponsorshipService;
use Illuminate\Http\JsonResponse;

/**
 * The sponsor strip on the public event page.
 *
 * Unauthenticated because it is part of the page anyone can read. The service returns only
 * signed sponsors the organizer chose to show, and the resource carries no commercial
 * detail: contract values and fulfilment stay behind SPONSOR_MANAGE.
 */
class GetPublicSponsorsAction extends BaseAction
{
    public function __construct(
        private readonly SponsorshipService $sponsorshipService,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        return $this->jsonResponse([
            'data' => $this->sponsorshipService->publicSponsors($eventId)
                ->map(static fn (object $row): array => (new PublicSponsorResource($row))->toArray(request()))
                ->values()
                ->all(),
        ]);
    }
}
