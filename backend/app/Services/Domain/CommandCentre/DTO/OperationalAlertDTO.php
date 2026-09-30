<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\CommandCentre\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

/**
 * Something an operator should act on.
 *
 * Deliberately about what attendees feel — a queue building, a room full, a door denying
 * everybody — rather than about infrastructure. A dashboard that alerts on everything trains
 * people to ignore it.
 */
class OperationalAlertDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $code,
        public readonly string $severity,
        public readonly string $subject,
        public readonly string $detail,
    ) {}

    public function isHigh(): bool
    {
        return $this->severity === 'HIGH';
    }
}
