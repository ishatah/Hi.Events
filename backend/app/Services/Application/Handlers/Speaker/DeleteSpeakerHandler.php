<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Speaker;

use HiEvents\DomainObjects\Generated\SpeakerDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\SpeakerRepositoryInterface;

class DeleteSpeakerHandler
{
    public function __construct(
        private readonly SpeakerRepositoryInterface $repository,
        private readonly GetSpeakerHandler $getSpeakerHandler,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, int $id): void
    {
        $this->getSpeakerHandler->handle($eventId, $id);

        $this->repository->deleteWhere([
            SpeakerDomainObjectAbstract::ID => $id,
            SpeakerDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }
}
