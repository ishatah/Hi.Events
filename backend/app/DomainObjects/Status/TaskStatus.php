<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum TaskStatus: string
{
    use BaseEnum;

    case TODO = 'TODO';
    case IN_PROGRESS = 'IN_PROGRESS';
    case DONE = 'DONE';
    case BLOCKED = 'BLOCKED';
    case WAIVED = 'WAIVED';

    /**
     * Whether this task still stands in the way of a go decision. A waived blocker no
     * longer blocks, but the waiver is recorded with a reason.
     */
    public function isOutstanding(): bool
    {
        return $this !== self::DONE && $this !== self::WAIVED;
    }
}
