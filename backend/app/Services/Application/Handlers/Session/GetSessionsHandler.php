<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Session;

use HiEvents\DomainObjects\Generated\SessionDomainObjectAbstract;
use HiEvents\Repository\Interfaces\SessionRepositoryInterface;
use Illuminate\Support\Collection;

class GetSessionsHandler
{
    public function __construct(
        private readonly SessionRepositoryInterface $repository,
    ) {}

    public function handle(int $eventId): Collection
    {
        return $this->repository->findWhere([
            SessionDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }
}
