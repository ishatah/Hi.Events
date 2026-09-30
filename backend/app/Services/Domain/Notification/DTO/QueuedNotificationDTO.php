<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Notification\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class QueuedNotificationDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $notificationId,
        public readonly int $queued,
        public readonly int $suppressed,
        public readonly int $deferred,
        public readonly int $skipped,
    ) {}

    /**
     * Recipients the platform could not reach on any channel the category allows. Reported
     * rather than swallowed: a gate change that reached nobody is an operational fact
     * somebody needs to know before the gate opens.
     */
    public function hasUnreachableRecipients(): bool
    {
        return $this->skipped > 0;
    }
}
