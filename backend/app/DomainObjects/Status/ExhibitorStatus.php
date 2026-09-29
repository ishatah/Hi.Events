<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum ExhibitorStatus: string
{
    use BaseEnum;

    case INVITED = 'INVITED';
    case APPLIED = 'APPLIED';
    case APPROVED = 'APPROVED';
    case CONTRACTED = 'CONTRACTED';
    case ACTIVE = 'ACTIVE';
    case CANCELLED = 'CANCELLED';

    /**
     * Whether staff may be named and passes requested.
     *
     * An invited or applied exhibitor has no contract yet, so naming staff would create
     * accreditations for a company that may never exhibit.
     */
    public function permitsStaffPasses(): bool
    {
        return $this === self::CONTRACTED || $this === self::ACTIVE;
    }

    public function isOpen(): bool
    {
        return $this !== self::CANCELLED;
    }
}
