<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Zone;

use HiEvents\DomainObjects\Generated\ZoneDomainObjectAbstract;
use HiEvents\DomainObjects\ZoneDomainObject;
use HiEvents\Repository\Interfaces\ZoneRepositoryInterface;
use Illuminate\Support\Str;

class CreateZoneHandler
{
    public function __construct(
        private readonly ZoneRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $venueId, array $attributes): ZoneDomainObject
    {
        return $this->repository->create(array_merge($attributes, [
            ZoneDomainObjectAbstract::SHORT_ID => 'zn_'.Str::lower(Str::random(20)),
            ZoneDomainObjectAbstract::VENUE_ID => $venueId,
        ]));
    }
}
