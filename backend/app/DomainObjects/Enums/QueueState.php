<?php

namespace HiEvents\DomainObjects\Enums;

/**
 * A qualitative state rather than a precise wait, because the underlying figure is an
 * estimate and a number shown as fact gets quoted back when it is wrong.
 */
enum QueueState: string
{
    use BaseEnum;

    case IDLE = 'IDLE';
    case FLOWING = 'FLOWING';
    case BUSY = 'BUSY';
    case CONGESTED = 'CONGESTED';

    public function needsAttention(): bool
    {
        return $this === self::BUSY || $this === self::CONGESTED;
    }
}
