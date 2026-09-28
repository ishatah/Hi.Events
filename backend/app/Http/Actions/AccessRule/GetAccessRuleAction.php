<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccessRule;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\AccessRule\AccessRuleResource;
use HiEvents\Services\Application\Handlers\AccessRule\GetAccessRuleHandler;
use Illuminate\Http\JsonResponse;

class GetAccessRuleAction extends BaseAction
{
    public function __construct(
        private readonly GetAccessRuleHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $id): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(AccessRuleResource::class, $this->handler->handle($eventId, $id));
    }
}
