<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccreditationType;

use HiEvents\DomainObjects\AccreditationTypeDomainObject;
use HiEvents\DomainObjects\Generated\AccreditationTypeDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AccreditationTypeRepositoryInterface;

class UpdateAccreditationTypeHandler
{
    public function __construct(
        private readonly AccreditationTypeRepositoryInterface $repository,
        private readonly GetAccreditationTypeHandler $getAccreditationTypeHandler,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, int $id, array $attributes): AccreditationTypeDomainObject
    {
        // Scoped read first, so an id belonging to another tenant cannot be updated.
        $this->getAccreditationTypeHandler->handle($eventId, $id);

        $this->repository->updateWhere(
            attributes: $attributes,
            where: [
                AccreditationTypeDomainObjectAbstract::ID => $id,
                AccreditationTypeDomainObjectAbstract::EVENT_ID => $eventId,
            ],
        );

        return $this->getAccreditationTypeHandler->handle($eventId, $id);
    }
}
