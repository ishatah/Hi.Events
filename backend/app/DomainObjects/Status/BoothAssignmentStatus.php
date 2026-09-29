<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum BoothAssignmentStatus: string
{
    use BaseEnum;

    case HELD = 'HELD';
    case ASSIGNED = 'ASSIGNED';
    case BUILT = 'BUILT';
    case RELEASED = 'RELEASED';

    /**
     * A held or assigned booth is spoken for. Only a release frees it, which is what the
     * partial unique index enforces.
     */
    public function occupiesBooth(): bool
    {
        return $this !== self::RELEASED;
    }
}
