<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Speaker;

use HiEvents\DomainObjects\Generated\SpeakerDomainObjectAbstract;
use HiEvents\Repository\Interfaces\SpeakerRepositoryInterface;
use Illuminate\Support\Collection;

class GetSpeakersHandler
{
    public function __construct(
        private readonly SpeakerRepositoryInterface $repository,
    ) {}

    public function handle(int $eventId): Collection
    {
        return $this->repository->findWhere([
            SpeakerDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }
}
