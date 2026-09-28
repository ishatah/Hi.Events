<?php

namespace HiEvents\DomainObjects\Status;

enum CredentialStatus: string
{
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case REVOKED = 'REVOKED';
    case EXPIRED = 'EXPIRED';

    public function permitsAccess(): bool
    {
        return $this === self::ACTIVE;
    }
}
