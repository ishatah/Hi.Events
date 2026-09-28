<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccreditationType;

use HiEvents\DomainObjects\Generated\AccreditationTypeDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AccreditationTypeRepositoryInterface;

class DeleteAccreditationTypeHandler
{
    public function __construct(
        private readonly AccreditationTypeRepositoryInterface $repository,
        private readonly GetAccreditationTypeHandler $getAccreditationTypeHandler,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, int $id): void
    {
        $this->getAccreditationTypeHandler->handle($eventId, $id);

        $this->repository->deleteWhere([
            AccreditationTypeDomainObjectAbstract::ID => $id,
            AccreditationTypeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }
}
