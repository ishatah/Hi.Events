<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Exhibitor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Exhibitor\UpdateLeadRequest;
use HiEvents\Resources\Exhibitor\LeadResource;
use HiEvents\Services\Application\Handlers\Exhibitor\UpdateLeadHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateLeadAction extends BaseAction
{
    public function __construct(
        private readonly UpdateLeadHandler $handler,
    ) {}

    public function __invoke(
        UpdateLeadRequest $request,
        int $eventId,
        int $eventExhibitorId,
        int $leadId
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::LEAD_VIEW);

        try {
            $lead = $this->handler->handle(
                leadId: $leadId,
                eventExhibitorId: $eventExhibitorId,
                rating: $request->validated('rating'),
                status: $request->validated('status'),
                notes: $request->validated('notes'),
                qualification: $request->validated('qualification'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['id' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['data' => (new LeadResource($lead))->toArray(request())]);
    }
}
