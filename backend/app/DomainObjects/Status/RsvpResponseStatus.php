<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum RsvpResponseStatus: string
{
    use BaseEnum;

    case ATTENDING = 'ATTENDING';
    case NOT_ATTENDING = 'NOT_ATTENDING';
    case TENTATIVE = 'TENTATIVE';

    public function bringsGuests(): bool
    {
        return $this !== self::NOT_ATTENDING;
    }
}
