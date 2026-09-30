<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Queue\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\QueueState;

class QueueEstimateDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $accessPointId,
        public readonly string $windowStart,
        public readonly string $windowEnd,
        public readonly int $scansGranted,
        public readonly int $scansDenied,
        public readonly float $throughputPerMinute,
        public readonly float $arrivalsPerMinute,
        public readonly ?float $medianServiceSeconds,
        public readonly int $estimatedQueueDepth,
        public readonly ?int $estimatedWaitSecondsLow,
        public readonly ?int $estimatedWaitSecondsHigh,
        public readonly QueueState $state,
        public readonly float $deniedShare,
    ) {}

    /**
     * A high denial share at one door usually means a misconfigured rule rather than a
     * crowd, and the two need opposite responses: fix the rule, or send more staff.
     */
    public function suggestsMisconfiguration(): bool
    {
        return $this->deniedShare >= 0.3 && ($this->scansGranted + $this->scansDenied) >= 10;
    }
}
