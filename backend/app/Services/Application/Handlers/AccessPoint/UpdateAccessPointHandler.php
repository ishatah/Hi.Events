<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccessPoint;

use HiEvents\DomainObjects\AccessPointDomainObject;
use HiEvents\DomainObjects\Generated\AccessPointDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AccessPointRepositoryInterface;

class UpdateAccessPointHandler
{
    public function __construct(
        private readonly AccessPointRepositoryInterface $repository,
        private readonly GetAccessPointHandler $getAccessPointHandler,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ResourceConflictException
     */
    public function handle(int $zoneId, int $id, array $attributes): AccessPointDomainObject
    {
        // Scoped read first, so an id under another parent cannot be updated.
        $this->getAccessPointHandler->handle($zoneId, $id);

        $this->repository->updateWhere(
            attributes: $attributes,
            where: [
                AccessPointDomainObjectAbstract::ID => $id,
                AccessPointDomainObjectAbstract::ZONE_ID => $zoneId,
            ],
        );

        return $this->getAccessPointHandler->handle($zoneId, $id);
    }
}
