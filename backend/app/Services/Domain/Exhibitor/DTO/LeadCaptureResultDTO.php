<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Exhibitor\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\LeadCaptureResolution;

class LeadCaptureResultDTO extends BaseDataObject
{
    public function __construct(
        public readonly LeadCaptureResolution $resolution,
        public readonly ?int $leadId = null,
        public readonly bool $replayed = false,
    ) {}

    public function transferredData(): bool
    {
        return $this->resolution->transferredData();
    }
}
