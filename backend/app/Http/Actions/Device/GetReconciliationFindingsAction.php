<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Device;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Device\AccessReconciliationService;
use Illuminate\Http\JsonResponse;

class GetReconciliationFindingsAction extends BaseAction
{
    public function __construct(
        private readonly AccessReconciliationService $reconciliation,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::ACCESS_LOGS_VIEW);

        return $this->jsonResponse([
            'summary' => $this->reconciliation->summary($eventId),
            'open' => $this->reconciliation->openFindings($eventId),
        ]);
    }
}
