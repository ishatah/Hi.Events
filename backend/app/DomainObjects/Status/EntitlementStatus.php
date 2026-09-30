<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum EntitlementStatus: string
{
    use BaseEnum;

    case PENDING = 'PENDING';
    case IN_PROGRESS = 'IN_PROGRESS';
    case FULFILLED = 'FULFILLED';
    case WAIVED = 'WAIVED';

    /**
     * A waived entitlement is settled, not outstanding: the sponsor agreed to drop it, so
     * counting it as owed would overstate what is still due.
     */
    public function isOutstanding(): bool
    {
        return $this === self::PENDING || $this === self::IN_PROGRESS;
    }
}
