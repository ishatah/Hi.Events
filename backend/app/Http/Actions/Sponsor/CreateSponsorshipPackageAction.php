<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Sponsor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Sponsor\UpsertSponsorshipPackageRequest;
use HiEvents\Services\Domain\Sponsor\SponsorshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CreateSponsorshipPackageAction extends BaseAction
{
    public function __construct(
        private readonly SponsorshipService $sponsorshipService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(UpsertSponsorshipPackageRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::SPONSOR_MANAGE);

        try {
            $packageId = $this->sponsorshipService->createPackage(
                eventId: $eventId,
                name: $request->validated('name'),
                tier: $request->validated('tier'),
                price: $request->input('price') !== null ? (float) $request->input('price') : null,
                currency: $request->input('currency'),
                entitlements: $request->input('entitlements'),
                maxSponsors: $request->input('max_sponsors') !== null
                    ? (int) $request->input('max_sponsors')
                    : null,
                sortOrder: (int) ($request->input('sort_order') ?? 0),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['entitlements' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['id' => $packageId], statusCode: 201);
    }
}
