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

}
