<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Networking;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Networking\MeetingService;
use Illuminate\Http\JsonResponse;

class GetPersonMeetingsAction extends BaseAction
{
    public function __construct(
        private readonly MeetingService $meetingService,
    ) {}

    public function __invoke(int $eventId, int $personId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::NETWORKING_MANAGE);

        return $this->jsonResponse([
            'data' => $this->meetingService->scheduleFor($eventId, $personId)->all(),
        ]);
    }
}
