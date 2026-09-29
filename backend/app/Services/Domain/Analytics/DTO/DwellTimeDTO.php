<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Analytics\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

/**
 * Dwell time, or an explicit statement that it cannot be measured.
 *
 * A nullable average would be read as zero somewhere downstream. This makes the
 * distinction between "nobody stayed" and "we cannot know" impossible to lose.
 */
class DwellTimeDTO extends BaseDataObject
{
    public function __construct(
        public readonly bool $measurable,
        public readonly int $sampleSize = 0,
        public readonly ?int $averageSeconds = null,
        public readonly ?int $medianSeconds = null,
        public readonly ?string $reason = null,
    ) {}

    public function hasData(): bool
    {
        return $this->measurable && $this->sampleSize > 0;
    }
}
