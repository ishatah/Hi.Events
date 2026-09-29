<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Device;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Device\AccessReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewReconciliationFindingAction extends BaseAction
{
    public function __construct(
        private readonly AccessReconciliationService $reconciliation,
    ) {}

    public function __invoke(Request $request, int $eventId, int $findingId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::ACCESS_LOGS_VIEW);

        try {
            $this->reconciliation->review(
                findingId: $findingId,
                reviewerUserId: $this->getAuthenticatedUser()->getId(),
                note: (string) ($request->input('note') ?? ''),
                outcome: (string) ($request->input('outcome') ?? 'REVIEWED'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['note' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['status' => 'REVIEWED']);
    }
}
