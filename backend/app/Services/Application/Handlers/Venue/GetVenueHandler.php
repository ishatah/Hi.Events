<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Venue;

use HiEvents\DomainObjects\Generated\VenueDomainObjectAbstract;
use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\VenueRepositoryInterface;

class GetVenueHandler
{
    public function __construct(
        private readonly VenueRepositoryInterface $repository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $accountId, int $id): VenueDomainObject
    {
        $record = $this->repository->findFirstWhere([
            VenueDomainObjectAbstract::ID => $id,
            VenueDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]);

        if ($record === null) {
            throw new ResourceNotFoundException(__('The requested record could not be found.'));
        }

        return $record;
    }
}
