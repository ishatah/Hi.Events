<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Sponsor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Sponsor\RecordFulfilmentRequest;
use HiEvents\Services\Domain\Sponsor\SponsorshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RecordEntitlementFulfilmentAction extends BaseAction
{
    public function __construct(
        private readonly SponsorshipService $sponsorshipService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(
        RecordFulfilmentRequest $request,
        int $eventId,
        int $entitlementId
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::SPONSOR_MANAGE);

        $waivedReason = $request->input('waived_reason');

        try {
            $this->sponsorshipService->assertEntitlementBelongsToEvent($entitlementId, $eventId);

            if ($waivedReason !== null) {
                $this->sponsorshipService->waiveEntitlement($entitlementId, (string) $waivedReason);
            } else {
                $this->sponsorshipService->recordFulfilment(
                    entitlementId: $entitlementId,
                    quantity: (int) ($request->input('quantity') ?? 1),
                    evidence: $request->input('evidence'),
                );
            }
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['recorded' => true]);
    }
}
