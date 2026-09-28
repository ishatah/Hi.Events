<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Venue;

use HiEvents\DomainObjects\Generated\VenueDomainObjectAbstract;
use HiEvents\Repository\Interfaces\VenueRepositoryInterface;
use Illuminate\Support\Collection;

class GetVenuesHandler
{
    public function __construct(
        private readonly VenueRepositoryInterface $repository,
    ) {}

    public function handle(int $accountId): Collection
    {
        return $this->repository->findWhere([
            VenueDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]);
    }
}
