<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Operations;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Operations\TaskTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InstantiateTaskTemplateAction extends BaseAction
{
    public function __construct(
        private readonly TaskTemplateService $taskTemplateService,
    ) {}

    public function __invoke(Request $request, int $eventId, int $templateId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::EVENT_UPDATE);

        try {
            $created = $this->taskTemplateService->instantiate(
                taskTemplateId: $templateId,
                eventId: $eventId,
                assigneeUserId: $request->input('assignee_user_id') !== null
                    ? (int) $request->input('assignee_user_id')
                    : null,
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['template_id' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['created' => $created]);
    }
}
