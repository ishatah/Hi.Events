<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Venue;

use HiEvents\DomainObjects\Generated\VenueDomainObjectAbstract;
use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\VenueRepositoryInterface;

class UpdateVenueHandler
{
    public function __construct(
        private readonly VenueRepositoryInterface $repository,
        private readonly GetVenueHandler $getVenueHandler,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ResourceConflictException
     */
    public function handle(int $accountId, int $id, array $attributes): VenueDomainObject
    {
        // Scoped read first, so an id belonging to another tenant cannot be updated.
        $this->getVenueHandler->handle($accountId, $id);

        $this->repository->updateWhere(
            attributes: $attributes,
            where: [
                VenueDomainObjectAbstract::ID => $id,
                VenueDomainObjectAbstract::ACCOUNT_ID => $accountId,
            ],
        );

        return $this->getVenueHandler->handle($accountId, $id);
    }
}
