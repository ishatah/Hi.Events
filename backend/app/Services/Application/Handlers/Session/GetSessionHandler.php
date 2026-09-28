<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Session;

use HiEvents\DomainObjects\Generated\SessionDomainObjectAbstract;
use HiEvents\DomainObjects\SessionDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\SessionRepositoryInterface;

class GetSessionHandler
{
    public function __construct(
        private readonly SessionRepositoryInterface $repository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $id): SessionDomainObject
    {
        $record = $this->repository->findFirstWhere([
            SessionDomainObjectAbstract::ID => $id,
            SessionDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($record === null) {
            throw new ResourceNotFoundException(__('The requested record could not be found.'));
        }

        return $record;
    }
}
