<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Raffle\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class RaffleDrawDTO extends BaseDataObject
{
    /**
     * @param  array<int, int>  $winnerPersonIds
     */
    public function __construct(
        public readonly int $raffleId,
        public readonly int $drawId,
        public readonly int $eligiblePoolSize,
        public readonly string $randomSeed,
        public readonly array $winnerPersonIds,
    ) {}

}
