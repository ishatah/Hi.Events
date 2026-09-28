<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Speaker;

use HiEvents\DomainObjects\Generated\SpeakerDomainObjectAbstract;
use HiEvents\DomainObjects\SpeakerDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\SpeakerRepositoryInterface;

class UpdateSpeakerHandler
{
    public function __construct(
        private readonly SpeakerRepositoryInterface $repository,
        private readonly GetSpeakerHandler $getSpeakerHandler,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, int $id, array $attributes): SpeakerDomainObject
    {
        // Scoped read first, so an id belonging to another tenant cannot be updated.
        $this->getSpeakerHandler->handle($eventId, $id);

        $this->repository->updateWhere(
            attributes: $attributes,
            where: [
                SpeakerDomainObjectAbstract::ID => $id,
                SpeakerDomainObjectAbstract::EVENT_ID => $eventId,
            ],
        );

        return $this->getSpeakerHandler->handle($eventId, $id);
    }
}
