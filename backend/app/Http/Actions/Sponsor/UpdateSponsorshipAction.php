<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Sponsor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Sponsor\UpdateSponsorshipRequest;
use HiEvents\Services\Domain\Sponsor\SponsorshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateSponsorshipAction extends BaseAction
{
    public function __construct(
        private readonly SponsorshipService $sponsorshipService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(
        UpdateSponsorshipRequest $request,
        int $eventId,
        int $sponsorshipId
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::SPONSOR_MANAGE);

        try {
            if ($request->input('status') !== null) {
                $this->sponsorshipService->transitionStatus(
                    $sponsorshipId,
                    $eventId,
                    (string) $request->input('status')
                );
            }

            if ($request->input('show_on_event_page') !== null) {
                $this->sponsorshipService->setPublicDisplay(
                    $sponsorshipId,
                    $eventId,
                    (bool) $request->input('show_on_event_page'),
                    (int) ($request->input('display_order') ?? 0),
                );
            }
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return $this->jsonResponse([
            'fulfilment' => $this->sponsorshipService->fulfilmentSummary($sponsorshipId),
        ]);
    }
}
