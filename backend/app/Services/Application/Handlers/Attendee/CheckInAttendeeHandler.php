<?php

namespace HiEvents\Services\Application\Handlers\Attendee;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Enums\AttendeeCheckInActionType;
use HiEvents\DomainObjects\Enums\CheckInAction;
use HiEvents\DomainObjects\Generated\AttendeeCheckInDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Repository\Interfaces\AttendeeCheckInRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Attendee\DTO\CheckInAttendeeDTO;
use HiEvents\Services\Application\Handlers\CheckInList\Public\DTO\AttendeeAndActionDTO;
use HiEvents\Services\Domain\CheckInList\CreateAttendeeCheckInService;
use HiEvents\Services\Domain\CheckInList\DeleteAttendeeCheckInService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\CheckinEvent;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Dashboard check-in.
 *
 * Delegates to the same domain services as the public scanner rather than writing
 * attendees.checked_in_at directly. The two paths previously produced different data — one
 * wrote columns, the other rows — and only the scanner fired checkin.created, so a
 * dashboard check-in was invisible to webhooks and to anything counting attendee_check_ins.
 * That is finding F10.
 *
 * Every event carries a system-default check-in list, created with the event and
 * backfilled for existing ones, which is what a dashboard check-in is attributed to.
 *
 * @see docs/arzo-master-plan/02-current-state-audit.md F10
 */
class CheckInAttendeeHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly AttendeeCheckInRepositoryInterface $attendeeCheckInRepository,
        private readonly CheckInListRepositoryInterface $checkInListRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly CreateAttendeeCheckInService $createAttendeeCheckInService,
        private readonly DeleteAttendeeCheckInService $deleteAttendeeCheckInService,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws CannotCheckInException
     * @throws ResourceNotFoundException
     */
    public function handle(CheckInAttendeeDTO $checkInAttendeeDTO): AttendeeDomainObject
    {
        $attendee = $this->fetchAttendee($checkInAttendeeDTO);

        $this->validateAttendeeStatus($attendee);
        $this->validateAction($attendee, $checkInAttendeeDTO);

        $checkInList = $this->systemDefaultCheckInList($checkInAttendeeDTO->event_id);

        if ($checkInAttendeeDTO->action === CheckInAction::CHECK_IN) {
            $this->checkIn($attendee, $checkInList, $checkInAttendeeDTO);
        } else {
            $this->checkOut($attendee, $checkInList);
        }

        return $this->fetchAttendee($checkInAttendeeDTO);
    }

    /**
     * @throws CannotCheckInException
     */
    private function checkIn(
        AttendeeDomainObject $attendee,
        CheckInListDomainObject $checkInList,
        CheckInAttendeeDTO $checkInAttendeeDTO,
    ): void {
        $result = $this->createAttendeeCheckInService->checkInAttendees(
            checkInListUuid: $checkInList->getShortId(),
            checkInUserIpAddress: request()->ip() ?? '',
            attendeesAndActions: new Collection([
                new AttendeeAndActionDTO(
                    public_id: $attendee->getPublicId(),
                    action: AttendeeCheckInActionType::CHECK_IN,
                ),
            ]),
        );

        if ($result->errors->errors !== []) {
            throw new CannotCheckInException((string) reset($result->errors->errors));
        }

        // The columns stay in step with the rows. They are what the attendee list and the
        // existing exports read, so dropping them here would silently blank the dashboard.
        $this->attendeeRepository->updateWhere(
            attributes: [
                AttendeeDomainObjectAbstract::CHECKED_IN_AT => now(),
                AttendeeDomainObjectAbstract::CHECKED_IN_BY => $checkInAttendeeDTO->checked_in_by_user_id,
                AttendeeDomainObjectAbstract::CHECKED_OUT_BY => null,
            ],
            where: [
                AttendeeDomainObjectAbstract::PUBLIC_ID => $attendee->getPublicId(),
                AttendeeDomainObjectAbstract::EVENT_ID => $attendee->getEventId(),
            ],
        );

        foreach ($result->attendeeCheckIns as $checkIn) {
            $this->domainEventDispatcherService->dispatch(
                new CheckinEvent(
                    type: DomainEventType::CHECKIN_CREATED,
                    attendeeCheckinId: $checkIn->getId(),
                )
            );
        }
    }

    /**
     * @throws CannotCheckInException
     */
    private function checkOut(AttendeeDomainObject $attendee, CheckInListDomainObject $checkInList): void
    {
        $checkIn = $this->attendeeCheckInRepository->findFirstWhere([
            AttendeeCheckInDomainObjectAbstract::ATTENDEE_ID => $attendee->getId(),
            AttendeeCheckInDomainObjectAbstract::CHECK_IN_LIST_ID => $checkInList->getId(),
        ]);

        if ($checkIn !== null) {
            $checkInId = $this->deleteAttendeeCheckInService->deleteAttendeeCheckIn(
                checkInListShortId: $checkInList->getShortId(),
                checkInShortId: $checkIn->getShortId(),
            );

            $this->domainEventDispatcherService->dispatch(
                new CheckinEvent(
                    type: DomainEventType::CHECKIN_DELETED,
                    attendeeCheckinId: $checkInId,
                )
            );
        }

        $this->attendeeRepository->updateWhere(
            attributes: [
                AttendeeDomainObjectAbstract::CHECKED_IN_AT => null,
                AttendeeDomainObjectAbstract::CHECKED_IN_BY => null,
                AttendeeDomainObjectAbstract::CHECKED_OUT_BY => null,
            ],
            where: [
                AttendeeDomainObjectAbstract::PUBLIC_ID => $attendee->getPublicId(),
                AttendeeDomainObjectAbstract::EVENT_ID => $attendee->getEventId(),
            ],
        );
    }

    /**
     * @throws CannotCheckInException
     */
    private function systemDefaultCheckInList(int $eventId): CheckInListDomainObject
    {
        $checkInList = $this->checkInListRepository->findFirstWhere([
            CheckInListDomainObjectAbstract::EVENT_ID => $eventId,
            CheckInListDomainObjectAbstract::IS_SYSTEM_DEFAULT => true,
        ]);

        if ($checkInList === null) {
            throw new CannotCheckInException(
                __('This event has no default check-in list.')
            );
        }

        return $checkInList;
    }

    private function fetchAttendee(CheckInAttendeeDTO $checkInAttendeeDTO): AttendeeDomainObject
    {
        $criteria = [
            AttendeeDomainObjectAbstract::PUBLIC_ID => $checkInAttendeeDTO->attendee_public_id,
            AttendeeDomainObjectAbstract::EVENT_ID => $checkInAttendeeDTO->event_id,
        ];

        $attendee = $this->attendeeRepository->findFirstWhere($criteria);

        if (! $attendee) {
            throw new ResourceNotFoundException;
        }

        return $attendee;
    }

    /**
     * @throws CannotCheckInException
     */
    private function validateAttendeeStatus(AttendeeDomainObject $attendee): void
    {
        if ($attendee->getStatus() !== AttendeeStatus::ACTIVE->name) {
            $this->logger->info(
                'Attempted to check in attendee that is not active',
                [
                    'attendee_public_id' => $attendee->getPublicId(),
                    'event_id' => $attendee->getEventId(),
                ]
            );

            throw new CannotCheckInException(__('Cannot check in attendee as they are not active.'));
        }
    }

    /**
     * @throws CannotCheckInException
     */
    private function validateAction(AttendeeDomainObject $attendee, CheckInAttendeeDTO $checkInAttendeeDTO): void
    {
        $actionName = $checkInAttendeeDTO->action === CheckInAction::CHECK_IN ? __('in') : __('out');
        $isInvalidCheckIn = $attendee->getCheckedInAt() !== null && $checkInAttendeeDTO->action === CheckInAction::CHECK_IN;
        $isInvalidCheckOut = $attendee->getCheckedInAt() === null && $checkInAttendeeDTO->action === CheckInAction::CHECK_OUT;

        if ($isInvalidCheckIn || $isInvalidCheckOut) {
            $actorId = $checkInAttendeeDTO->action === CheckInAction::CHECK_IN
                ? $attendee->getCheckedInBy()
                : $attendee->getCheckedOutBy();

            // Checking out somebody who was never checked in leaves no actor to name, and
            // looking up a null id threw a TypeError instead of reporting the real problem.
            $actor = $actorId !== null ? $this->userRepository->findById($actorId) : null;

            if ($actor === null) {
                throw new CannotCheckInException(
                    __('Cannot check :actionName attendee as they are already checked :actionName.', [
                        'actionName' => $actionName,
                    ])
                );
            }

            throw new CannotCheckInException(
                __(
                    'Cannot check :actionName attendee as they were already checked :actionName by :fullName :time.',
                    [
                        'actionName' => $actionName,
                        'fullName' => $actor->getFullName(),
                        'time' => $checkInAttendeeDTO->action === CheckInAction::CHECK_IN
                            ? Carbon::createFromTimeString($attendee->getCheckedInAt())->ago()
                            : '',
                    ]
                )
            );
        }
    }
}
