<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccreditationType;

use HiEvents\DomainObjects\Generated\AccreditationTypeDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AccreditationTypeRepositoryInterface;
use Illuminate\Support\Collection;

class GetAccreditationTypesHandler
{
    public function __construct(
        private readonly AccreditationTypeRepositoryInterface $repository,
    ) {}

    public function handle(int $eventId): Collection
    {
        return $this->repository->findWhere([
            AccreditationTypeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }
}
