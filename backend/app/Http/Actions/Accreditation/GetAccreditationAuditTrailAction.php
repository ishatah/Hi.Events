<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accreditation;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Accreditation\GetAccreditationAuditTrailHandler;
use Illuminate\Http\JsonResponse;

class GetAccreditationAuditTrailAction extends BaseAction
{
    public function __construct(
        private readonly GetAccreditationAuditTrailHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $accreditationId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::ACCREDITATION_VIEW);

        return $this->jsonResponse(['data' => $this->handler->handle($accreditationId)->all()]);
    }
}
