<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccessRule;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\AccessRule\AccessRuleResource;
use HiEvents\Services\Application\Handlers\AccessRule\GetAccessRulesHandler;
use Illuminate\Http\JsonResponse;

class GetAccessRulesAction extends BaseAction
{
    public function __construct(
        private readonly GetAccessRulesHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(AccessRuleResource::class, $this->handler->handle($eventId));
    }
}
