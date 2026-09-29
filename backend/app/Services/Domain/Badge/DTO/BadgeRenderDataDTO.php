<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BadgeRenderDataDTO extends BaseDataObject
{
    /**
     * @param  array<int, string>  $zoneNames
     * @param  array<int, string>  $zoneColours
     */
    public function __construct(
        public readonly int $credentialId,
        public readonly string $identifier,
        public readonly string $credentialType,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $company,
        public readonly ?string $jobTitle,
        public readonly ?string $accreditationTypeName,
        public readonly string $eventName,
        public readonly ?string $eventStartDate,
        public readonly ?string $eventEndDate,
        public readonly array $zoneNames,
        public readonly array $zoneColours,
        public readonly ?string $photoUrl,
    ) {}
}
