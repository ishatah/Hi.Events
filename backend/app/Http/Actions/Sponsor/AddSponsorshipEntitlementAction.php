<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Sponsor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Sponsor\AddEntitlementRequest;
use HiEvents\Services\Domain\Sponsor\SponsorshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AddSponsorshipEntitlementAction extends BaseAction
{
    public function __construct(
        private readonly SponsorshipService $sponsorshipService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(
        AddEntitlementRequest $request,
        int $eventId,
        int $sponsorshipId
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::SPONSOR_MANAGE);

        try {
            $this->sponsorshipService->assertBelongsToEvent($sponsorshipId, $eventId);

            $entitlementId = $this->sponsorshipService->addEntitlement(
                sponsorshipId: $sponsorshipId,
                entitlementType: $request->validated('entitlement_type'),
                quantity: (int) ($request->input('quantity') ?? 1),
                description: $request->input('description'),
                dueAt: $request->input('due_at'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['entitlement_type' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['id' => $entitlementId], statusCode: 201);
    }
}
