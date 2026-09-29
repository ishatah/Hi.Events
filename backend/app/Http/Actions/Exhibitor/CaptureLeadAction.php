<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Exhibitor;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Exhibitor\CaptureLeadRequest;
use HiEvents\Services\Domain\Exhibitor\LeadCaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CaptureLeadAction extends BaseAction
{
    public function __construct(
        private readonly LeadCaptureService $leadCaptureService,
    ) {}

    public function __invoke(
        CaptureLeadRequest $request,
        int $eventId,
        int $eventExhibitorId
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::LEAD_VIEW);

        try {
            $result = $this->leadCaptureService->capture(
                eventExhibitorId: $eventExhibitorId,
                identifier: (string) $request->validated('identifier'),
                capturedByStaffId: $request->validated('exhibitor_staff_id') !== null
                    ? (int) $request->validated('exhibitor_staff_id')
                    : null,
                clientGeneratedId: $request->validated('client_generated_id'),
                capturedAt: $request->validated('captured_at') !== null
                    ? Carbon::parse((string) $request->validated('captured_at'))
                    : null,
                identifierType: (string) ($request->validated('identifier_type') ?? 'QR'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['identifier' => $exception->getMessage()]);
        }

        // A capture that transferred nothing is still a recorded scan, so this is 200 with a
        // resolution rather than an error. The booth needs to know which of the four
        // outcomes it got.
        return $this->jsonResponse([
            'resolution' => $result->resolution->value,
            'transferred_data' => $result->transferredData(),
            'lead_id' => $result->leadId,
            'replayed' => $result->replayed,
        ]);
    }
}
