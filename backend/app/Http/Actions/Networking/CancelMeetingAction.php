<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Networking;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Networking\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Validation\ValidationException;

class CancelMeetingAction extends BaseAction
{
    public function __construct(
        private readonly MeetingService $meetingService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(int $eventId, int $meetingId): LaravelResponse|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::NETWORKING_MANAGE);

        try {
            $this->meetingService->cancel($meetingId, $eventId);
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['meeting_id' => $exception->getMessage()]);
        }

        return $this->deletedResponse();
    }
}
