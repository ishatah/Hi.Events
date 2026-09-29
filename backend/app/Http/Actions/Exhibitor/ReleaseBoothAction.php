<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Exhibitor;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Exhibitor\BoothAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Validation\ValidationException;

class ReleaseBoothAction extends BaseAction
{
    public function __construct(
        private readonly BoothAssignmentService $boothAssignmentService,
    ) {}

    public function __invoke(int $eventId, int $assignmentId): LaravelResponse|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::EXHIBITOR_MANAGE);

        $reason = (string) (request()->input('reason') ?? '');

        try {
            $this->boothAssignmentService->release($assignmentId, $reason !== '' ? $reason : __('Released'));
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['id' => $exception->getMessage()]);
        }

        return $this->deletedResponse();
    }
}
