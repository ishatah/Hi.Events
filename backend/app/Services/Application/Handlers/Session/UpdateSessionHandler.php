<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Session;

use HiEvents\DomainObjects\Generated\SessionDomainObjectAbstract;
use HiEvents\DomainObjects\SessionDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\SessionRepositoryInterface;

class UpdateSessionHandler
{
    public function __construct(
        private readonly SessionRepositoryInterface $repository,
        private readonly GetSessionHandler $getSessionHandler,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, int $id, array $attributes): SessionDomainObject
    {
        // Scoped read first, so an id belonging to another tenant cannot be updated.
        $this->getSessionHandler->handle($eventId, $id);

        $this->repository->updateWhere(
            attributes: $attributes,
            where: [
                SessionDomainObjectAbstract::ID => $id,
                SessionDomainObjectAbstract::EVENT_ID => $eventId,
            ],
        );

        return $this->getSessionHandler->handle($eventId, $id);
    }
}
