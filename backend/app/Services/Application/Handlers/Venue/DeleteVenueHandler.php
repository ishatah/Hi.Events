<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Venue;

use HiEvents\DomainObjects\Generated\VenueDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\VenueRepositoryInterface;

class DeleteVenueHandler
{
    public function __construct(
        private readonly VenueRepositoryInterface $repository,
        private readonly GetVenueHandler $getVenueHandler,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $accountId, int $id): void
    {
        $this->getVenueHandler->handle($accountId, $id);

        $this->repository->deleteWhere([
            VenueDomainObjectAbstract::ID => $id,
            VenueDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]);
    }
}
