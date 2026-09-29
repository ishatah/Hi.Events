<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Exhibitor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Exhibitor\UpsertEventExhibitorRequest;
use HiEvents\Resources\Exhibitor\EventExhibitorResource;
use HiEvents\Services\Application\Handlers\Exhibitor\UpsertEventExhibitorHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpsertEventExhibitorAction extends BaseAction
{
    public function __construct(
        private readonly UpsertEventExhibitorHandler $handler,
    ) {}

    public function __invoke(UpsertEventExhibitorRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::EXHIBITOR_MANAGE);

        try {
            $exhibitor = $this->handler->handle(
                eventId: $eventId,
                accountId: $this->getAuthenticatedAccountId(),
                companyId: (int) $request->validated('company_id'),
                status: (string) $request->validated('status'),
                packageName: $request->validated('package_name'),
                staffPassQuota: $request->validated('staff_pass_quota') !== null
                    ? (int) $request->validated('staff_pass_quota')
                    : null,
                contractValue: $request->validated('contract_value') !== null
                    ? (float) $request->validated('contract_value')
                    : null,
                currency: $request->validated('currency'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['company_id' => $exception->getMessage()]);
        }

        // A query-builder row is not a domain object, so the resource is applied directly
        // rather than through resourceResponse(), which only accepts the domain types.
        return $this->jsonResponse(['data' => (new EventExhibitorResource($exhibitor))->toArray(request())]);
    }
}
