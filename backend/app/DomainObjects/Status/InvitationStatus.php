<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum InvitationStatus: string
{
    use BaseEnum;

    case PENDING = 'PENDING';
    case SENT = 'SENT';
    case ATTENDING = 'ATTENDING';
    case NOT_ATTENDING = 'NOT_ATTENDING';
    case TENTATIVE = 'TENTATIVE';
    case REVOKED = 'REVOKED';

    public static function fromResponse(RsvpResponseStatus $response): self
    {
        return self::from($response->value);
    }
}
