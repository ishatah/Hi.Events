<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum SponsorshipStatus: string
{
    use BaseEnum;

    case PROPOSED = 'PROPOSED';
    case CONTRACTED = 'CONTRACTED';
    case ACTIVE = 'ACTIVE';
    case FULFILLED = 'FULFILLED';
    case CANCELLED = 'CANCELLED';

    /**
     * A proposal is not a relationship. Showing one on the public page announces a deal
     * that has not been signed.
     */
    public function isPubliclyDisplayable(): bool
    {
        return $this === self::CONTRACTED || $this === self::ACTIVE || $this === self::FULFILLED;
    }
}
