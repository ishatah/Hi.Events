<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum SessionRegistrationStatus: string
{
    use BaseEnum;

    case REGISTERED = 'REGISTERED';
    case CANCELLED = 'CANCELLED';

    public function occupiesCapacity(): bool
    {
        return $this === self::REGISTERED;
    }
}
