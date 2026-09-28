<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccreditationType;

use HiEvents\DomainObjects\AccreditationTypeDomainObject;
use HiEvents\DomainObjects\Generated\AccreditationTypeDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AccreditationTypeRepositoryInterface;

class GetAccreditationTypeHandler
{
    public function __construct(
        private readonly AccreditationTypeRepositoryInterface $repository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $id): AccreditationTypeDomainObject
    {
        $record = $this->repository->findFirstWhere([
            AccreditationTypeDomainObjectAbstract::ID => $id,
            AccreditationTypeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($record === null) {
            throw new ResourceNotFoundException(__('The requested record could not be found.'));
        }

        return $record;
    }
}
