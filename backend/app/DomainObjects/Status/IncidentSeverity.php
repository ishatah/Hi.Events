<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum IncidentSeverity: string
{
    use BaseEnum;

    case SEV1 = 'SEV1';
    case SEV2 = 'SEV2';
    case SEV3 = 'SEV3';
    case SEV4 = 'SEV4';

    /**
     * Minutes within which an incident should be acknowledged.
     *
     * Acknowledgement rather than resolution: the measure of a control room is how fast
     * somebody takes ownership, not how fast the problem goes away.
     */
    public function acknowledgementTargetMinutes(): int
    {
        return match ($this) {
            self::SEV1 => 2,
            self::SEV2 => 10,
            self::SEV3 => 30,
            self::SEV4 => 120,
        };
    }

    public function requiresImmediateEscalation(): bool
    {
        return $this === self::SEV1;
    }
}
