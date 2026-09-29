<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum IncidentStatus: string
{
    use BaseEnum;

    case OPEN = 'OPEN';
    case ACKNOWLEDGED = 'ACKNOWLEDGED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case RESOLVED = 'RESOLVED';
    case CLOSED = 'CLOSED';

    public function isOpen(): bool
    {
        return $this !== self::RESOLVED && $this !== self::CLOSED;
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::OPEN => [self::ACKNOWLEDGED, self::IN_PROGRESS, self::RESOLVED],
            self::ACKNOWLEDGED => [self::IN_PROGRESS, self::RESOLVED],
            self::IN_PROGRESS => [self::RESOLVED],
            // Reopening is deliberate and goes back to IN_PROGRESS, so the reopen is visible
            // in the update trail rather than looking like it was never resolved.
            self::RESOLVED => [self::CLOSED, self::IN_PROGRESS],
            self::CLOSED => [],
        }, true);
    }
}
