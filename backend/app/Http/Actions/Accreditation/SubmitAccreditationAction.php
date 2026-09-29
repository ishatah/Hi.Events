<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accreditation;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Accreditation\SubmitAccreditationRequest;
use HiEvents\Services\Application\Handlers\Accreditation\SubmitAccreditationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SubmitAccreditationAction extends BaseAction
{
    public function __construct(
        private readonly SubmitAccreditationHandler $handler,
    ) {}

    public function __invoke(SubmitAccreditationRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $accreditationId = $this->handler->handle(
                eventId: $eventId,
                personId: (int) $request->validated('person_id'),
                accreditationTypeId: (int) $request->validated('accreditation_type_id'),
                requestedZoneIds: $request->validated('requested_zones'),
                formData: $request->validated('form_data'),
                actorUserId: $this->getAuthenticatedUser()->getId(),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages([
                'accreditation_type_id' => $exception->getMessage(),
            ]);
        }

        return $this->jsonResponse(['id' => $accreditationId]);
    }
}
