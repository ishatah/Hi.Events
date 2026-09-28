<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Track;

use HiEvents\DomainObjects\Generated\TrackDomainObjectAbstract;
use HiEvents\Repository\Interfaces\TrackRepositoryInterface;
use Illuminate\Support\Collection;

class GetTracksHandler
{
    public function __construct(
        private readonly TrackRepositoryInterface $repository,
    ) {}

    public function handle(int $eventId): Collection
    {
        return $this->repository->findWhere([
            TrackDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }
}
