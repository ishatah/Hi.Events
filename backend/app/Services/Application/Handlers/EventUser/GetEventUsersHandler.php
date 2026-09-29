<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventUser;

use HiEvents\DomainObjects\Generated\EventUserDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EventUserRepositoryInterface;
use Illuminate\Support\Collection;

class GetEventUsersHandler
{
    public function __construct(
        private readonly EventUserRepositoryInterface $repository,
    ) {}

    public function handle(int $eventId): Collection
    {
        return $this->repository->findWhere([
            EventUserDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }
}
