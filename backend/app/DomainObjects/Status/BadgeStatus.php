<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum BadgeStatus: string
{
    use BaseEnum;

    case PENDING = 'PENDING';
    case PRINTED = 'PRINTED';
    case VOIDED = 'VOIDED';
    case REPLACED = 'REPLACED';

    public function isUsable(): bool
    {
        return $this === self::PENDING || $this === self::PRINTED;
    }
}
