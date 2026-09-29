<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum BadgePrintJobStatus: string
{
    use BaseEnum;

    case QUEUED = 'QUEUED';
    case SENT = 'SENT';
    case CONFIRMED = 'CONFIRMED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';

    public function isTerminal(): bool
    {
        return $this === self::CONFIRMED || $this === self::CANCELLED;
    }

    public function isRetryable(): bool
    {
        return $this === self::FAILED || $this === self::SENT;
    }
}
