<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Session;

use HiEvents\DomainObjects\Generated\SessionDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\SessionRepositoryInterface;

class DeleteSessionHandler
{
    public function __construct(
        private readonly SessionRepositoryInterface $repository,
        private readonly GetSessionHandler $getSessionHandler,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, int $id): void
    {
        $this->getSessionHandler->handle($eventId, $id);

        $this->repository->deleteWhere([
            SessionDomainObjectAbstract::ID => $id,
            SessionDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }
}
