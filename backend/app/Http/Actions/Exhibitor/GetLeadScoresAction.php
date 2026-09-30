<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Exhibitor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Exhibitor\LeadScoringService;
use Illuminate\Http\JsonResponse;

class GetLeadScoresAction extends BaseAction
{
    public function __construct(
        private readonly LeadScoringService $leadScoringService,
    ) {}

    public function __invoke(int $eventId, int $eventExhibitorId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::LEAD_VIEW);

        return $this->jsonResponse(['data' => $this->leadScoringService->rankForExhibitor($eventExhibitorId)]);
    }
}
