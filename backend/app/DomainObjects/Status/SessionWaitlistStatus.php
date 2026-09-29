<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum SessionWaitlistStatus: string
{
    use BaseEnum;

    case WAITING = 'WAITING';
    case OFFERED = 'OFFERED';
    case ACCEPTED = 'ACCEPTED';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';

    /**
     * An offer holds a seat until it expires, so an outstanding offer counts against
     * capacity. Releasing the seat the moment it is offered would let the next promotion
     * hand the same seat to somebody else.
     */
    public function holdsSeat(): bool
    {
        return $this === self::OFFERED;
    }

    public function isQueued(): bool
    {
        return $this === self::WAITING;
    }
}
