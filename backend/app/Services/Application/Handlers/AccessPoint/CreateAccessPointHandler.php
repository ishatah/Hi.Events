<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccessPoint;

use HiEvents\DomainObjects\AccessPointDomainObject;
use HiEvents\DomainObjects\Generated\AccessPointDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AccessPointRepositoryInterface;
use Illuminate\Support\Str;

class CreateAccessPointHandler
{
    public function __construct(
        private readonly AccessPointRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $zoneId, array $attributes): AccessPointDomainObject
    {
        return $this->repository->create(array_merge($attributes, [
            AccessPointDomainObjectAbstract::SHORT_ID => 'ap_'.Str::lower(Str::random(20)),
            AccessPointDomainObjectAbstract::ZONE_ID => $zoneId,
        ]));
    }
}
