<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Operations;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Operations\DecideReadinessRequest;
use HiEvents\Services\Domain\Operations\ReadinessReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DecideReadinessReviewAction extends BaseAction
{
    public function __construct(
        private readonly ReadinessReviewService $readinessReviewService,
    ) {}

    public function __invoke(
        DecideReadinessRequest $request,
        int $eventId,
        int $reviewId
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::EVENT_PUBLISH);

        try {
            $this->readinessReviewService->decide(
                reviewId: $reviewId,
                decision: (string) $request->validated('decision'),
                decidedByUserId: $this->getAuthenticatedUser()->getId(),
                notes: $request->validated('notes'),
                waivers: (array) ($request->validated('waivers') ?? []),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['decision' => $request->validated('decision')]);
    }
}
