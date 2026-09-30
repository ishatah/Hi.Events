<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Sponsor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Sponsor\SponsorshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class GetSponsorshipFulfilmentAction extends BaseAction
{
    public function __construct(
        private readonly SponsorshipService $sponsorshipService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(int $eventId, int $sponsorshipId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::SPONSOR_MANAGE);

        try {
            $this->sponsorshipService->assertBelongsToEvent($sponsorshipId, $eventId);
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['sponsorship_id' => $exception->getMessage()]);
        }

        return $this->jsonResponse($this->sponsorshipService->fulfilmentSummary($sponsorshipId));
    }
}
