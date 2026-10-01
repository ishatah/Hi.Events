<?php

namespace HiEvents\Resources\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Resources\CheckInList\AttendeeCheckInPublicResource;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResourcePublic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendeeDomainObject
 */
class AttendeeWithCheckInPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            // Deliberately not public_id: that value is the ticket QR payload, so a list
            // endpoint returning every attendee's would let anyone holding the link mint
            // every ticket. short_id identifies an attendee for check-in without proving
            // anything about ticket ownership.
            'short_id' => $this->getShortId(),
            'product_id' => $this->getProductId(),
            'product_price_id' => $this->getProductPriceId(),
            'status' => $this->getStatus(),
            'locale' => $this->getLocale(),
            'order_id' => $this->getOrderId(),
            'event_occurrence_id' => $this->getEventOccurrenceId(),
            'event_occurrence' => $this->getEventOccurrence()
                ? (new EventOccurrenceResourcePublic($this->getEventOccurrence()))->toArray($request)
                : null,
            $this->mergeWhen($this->getCheckIn() !== null, [
                'check_in' => new AttendeeCheckInPublicResource($this->getCheckIn()),
            ]),
        ];
    }
}
