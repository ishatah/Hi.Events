<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum ShiftAssignmentStatus: string
{
    use BaseEnum;

    case ASSIGNED = 'ASSIGNED';
    case CONFIRMED = 'CONFIRMED';
    case DECLINED = 'DECLINED';
    case CHECKED_IN = 'CHECKED_IN';
    case COMPLETED = 'COMPLETED';
    case NO_SHOW = 'NO_SHOW';
    case CANCELLED = 'CANCELLED';

    /**
     * A declined or cancelled assignment is no longer a commitment, which is why the
     * exclusion constraint ignores those two.
     */
    public function isCommitment(): bool
    {
        return $this !== self::DECLINED && $this !== self::CANCELLED;
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::ASSIGNED => [self::CONFIRMED, self::DECLINED, self::CHECKED_IN, self::NO_SHOW, self::CANCELLED],
            self::CONFIRMED => [self::CHECKED_IN, self::NO_SHOW, self::CANCELLED],
            self::CHECKED_IN => [self::COMPLETED, self::CANCELLED],
            // Terminal. Re-rostering somebody means a new assignment, so the history of who
            // was expected where survives.
            self::DECLINED, self::COMPLETED, self::NO_SHOW, self::CANCELLED => [],
        };
    }
}
