<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Access\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\AccessResult;

class AccessDecisionDTO extends BaseDataObject
{
    public function __construct(
        public readonly AccessResult $result,
        public readonly ?int $credentialId = null,
        public readonly ?int $matchedGrantId = null,
        public readonly ?int $matchedRuleId = null,
        public readonly ?string $reason = null,
    ) {}

    public function isGranted(): bool
    {
        return $this->result->isGranted();
    }
}
