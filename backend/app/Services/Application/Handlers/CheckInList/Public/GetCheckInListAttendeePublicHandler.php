<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Domain\CheckInList\CheckInListActivityValidator;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetCheckInListAttendeePublicHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly CheckInListRepositoryInterface $checkInListRepository,
        private readonly CheckInListActivityValidator $checkInListActivityValidator,
    ) {}

    /**
     * @throws CannotCheckInException
     */
    public function handle(string $shortId, string $attendeeShortId): AttendeeDomainObject
    {
        return $this->resolve($shortId, 'short_id', $attendeeShortId);
    }

    /**
     * Resolves a scanned ticket QR to its attendee.
     *
     * Separate from handle() because the two identifiers mean different things: short_id is a
     * lookup key an operator may see, while public_id is the ticket QR and may only travel
     * inward. Sharing one method would invite a caller to pass the wrong one.
     *
     * @throws CannotCheckInException
     */
    public function handleByPublicId(string $shortId, string $ticketCode): AttendeeDomainObject
    {
        return $this->resolve($shortId, 'public_id', $ticketCode);
    }

    /**
     * @throws CannotCheckInException
     */
    private function resolve(string $shortId, string $field, string $value): AttendeeDomainObject
    {
        $checkInList = $this->checkInListRepository
            ->loadRelation(ProductDomainObject::class)
            ->loadRelation(new Relationship(EventDomainObject::class, name: 'event'))
            ->findFirstWhere([
                CheckInListDomainObjectAbstract::SHORT_ID => $shortId,
            ]);

        if (! $checkInList) {
            throw new ResourceNotFoundException(__('Check-in list not found'));
        }

        $this->checkInListActivityValidator->assertActive($checkInList);

        $attendee = $this->attendeeRepository->findFirstWhere([
            $field => $value,
            'event_id' => $checkInList->getEventId(),
        ]);

        if (! $attendee) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        $this->verifyAttendeeBelongsToCheckInList($checkInList, $attendee);

        return $attendee;
    }

    private function verifyAttendeeBelongsToCheckInList(
        CheckInListDomainObject $checkInList,
        AttendeeDomainObject $attendee,
    ): void {
        $allowedProductIds = $checkInList->getProducts()?->map(fn ($product) => $product->getId())->toArray() ?? [];

        if (! empty($allowedProductIds) && ! in_array($attendee->getProductId(), $allowedProductIds, true)) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        if ($checkInList->getEventOccurrenceId() !== null
            && $attendee->getEventOccurrenceId() !== $checkInList->getEventOccurrenceId()
        ) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }
    }
}
