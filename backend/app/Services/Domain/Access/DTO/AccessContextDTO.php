<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Access\DTO;

use Carbon\CarbonInterface;
use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\AccessDirection;

/**
 * Everything an access decision needs, gathered before evaluation.
 *
 * The decision is a pure function of this context, which is what lets the identical
 * logic run on the server and on an offline device. Two implementations that disagree at
 * a door is the worst possible outcome, so nothing here is fetched lazily.
 *
 * @see docs/arzo-master-plan/24-access-control.md
 */
class AccessContextDTO extends BaseDataObject
{
    /**
     * @param  array<int, object>  $grants  Grants already filtered to this credential
     * @param  array<int, object>  $rules  Active rules for the event, priority ordered
     */
    public function __construct(
        public readonly ?object $credential,
        public readonly array $grants,
        public readonly array $rules,
        public readonly int $accessPointId,
        public readonly ?int $zoneId,
        public readonly AccessDirection $direction,
        public readonly CarbonInterface $occurredAt,
        public readonly string $venueTimezone = 'UTC',
        public readonly ?object $lastLogForZone = null,
        public readonly int $entryCountForZone = 0,
        public readonly ?int $zoneOccupancy = null,
        public readonly ?int $zoneCapacity = null,
    ) {}
}
