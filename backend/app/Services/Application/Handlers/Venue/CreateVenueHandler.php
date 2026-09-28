<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Venue;

use HiEvents\DomainObjects\Generated\VenueDomainObjectAbstract;
use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Repository\Interfaces\VenueRepositoryInterface;
use Illuminate\Support\Str;

class CreateVenueHandler
{
    public function __construct(
        private readonly VenueRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $accountId, array $attributes): VenueDomainObject
    {
        return $this->repository->create(array_merge($attributes, [
            VenueDomainObjectAbstract::SHORT_ID => 'vn_'.Str::lower(Str::random(20)),
            VenueDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]));
    }
}
