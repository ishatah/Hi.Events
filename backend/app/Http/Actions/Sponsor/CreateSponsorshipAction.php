<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Sponsor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Sponsor\CreateSponsorshipRequest;
use HiEvents\Services\Domain\Sponsor\SponsorshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CreateSponsorshipAction extends BaseAction
{
    public function __construct(
        private readonly SponsorshipService $sponsorshipService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(CreateSponsorshipRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::SPONSOR_MANAGE);

        try {
            $sponsorshipId = $this->sponsorshipService->createSponsorship(
                eventId: $eventId,
                companyId: (int) $request->validated('company_id'),
                tier: $request->validated('tier'),
                packageId: $request->input('sponsorship_package_id') !== null
                    ? (int) $request->input('sponsorship_package_id')
                    : null,
                contractValue: $request->input('contract_value') !== null
                    ? (float) $request->input('contract_value')
                    : null,
                currency: $request->input('currency'),
                displayName: $request->input('display_name'),
                websiteUrl: $request->input('website_url'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['company_id' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['id' => $sponsorshipId], statusCode: 201);
    }
}
