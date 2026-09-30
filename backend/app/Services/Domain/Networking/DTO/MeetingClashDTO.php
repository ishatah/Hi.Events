<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Networking\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

/**
 * One person already committed elsewhere for part of a proposed slot.
 *
 * Returned at request time so the requester can see it and choose another slot, and raised as
 * an error at confirmation so the double-booking never becomes a promise.
 */
class MeetingClashDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $personId,
        public readonly int $meetingId,
        public readonly string $startsAt,
        public readonly string $endsAt,
    ) {}
}
