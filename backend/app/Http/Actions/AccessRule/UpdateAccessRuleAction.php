<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\AccessRule;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\AccessRule\UpsertAccessRuleRequest;
use HiEvents\Resources\AccessRule\AccessRuleResource;
use HiEvents\Services\Application\Handlers\AccessRule\UpdateAccessRuleHandler;
use Illuminate\Http\JsonResponse;

class UpdateAccessRuleAction extends BaseAction
{
    public function __construct(
        private readonly UpdateAccessRuleHandler $handler,
    ) {}

    public function __invoke(UpsertAccessRuleRequest $request, int $eventId, int $id): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $record = $this->handler->handle($eventId, $id, $request->validated());

        return $this->resourceResponse(AccessRuleResource::class, $record);
    }
}
