<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Operations;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Operations\ReadinessReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenReadinessReviewAction extends BaseAction
{
    public function __construct(
        private readonly ReadinessReviewService $readinessReviewService,
    ) {}

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::EVENT_UPDATE);

        $reviewPoint = (string) ($request->input('review_point') ?? 'AD_HOC');

        $reviewId = $this->readinessReviewService->open($eventId, $reviewPoint);

        return $this->jsonResponse([
            'id' => $reviewId,
            'items' => $this->readinessReviewService->items($reviewId),
        ]);
    }
}
