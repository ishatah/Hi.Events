<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Zone;

use HiEvents\DomainObjects\Generated\ZoneDomainObjectAbstract;
use HiEvents\DomainObjects\ZoneDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\ZoneRepositoryInterface;

class UpdateZoneHandler
{
    public function __construct(
        private readonly ZoneRepositoryInterface $repository,
        private readonly GetZoneHandler $getZoneHandler,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ResourceConflictException
     */
    public function handle(int $venueId, int $id, array $attributes): ZoneDomainObject
    {
        // Scoped read first, so an id under another parent cannot be updated.
        $this->getZoneHandler->handle($venueId, $id);

        $this->repository->updateWhere(
            attributes: $attributes,
            where: [
                ZoneDomainObjectAbstract::ID => $id,
                ZoneDomainObjectAbstract::VENUE_ID => $venueId,
            ],
        );

        return $this->getZoneHandler->handle($venueId, $id);
    }
}
