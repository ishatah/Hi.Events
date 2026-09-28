<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Space;

use HiEvents\DomainObjects\Generated\ZoneDomainObjectAbstract;
use HiEvents\Repository\Interfaces\ZoneRepositoryInterface;

/**
 * Resolves the venue that owns a zone.
 *
 * Access points are addressed under a zone but authorized through the venue's account,
 * because a zone carries no account_id of its own.
 */
class ZoneScopeService
{
    public function __construct(
        private readonly ZoneRepositoryInterface $zoneRepository,
    ) {}

    public function venueIdForZone(int $zoneId): int
    {
        $zone = $this->zoneRepository->findFirstWhere([
            ZoneDomainObjectAbstract::ID => $zoneId,
        ]);

        // 0 never matches a venue, so an unknown zone fails the authorization check rather
        // than throwing an unhandled error.
        return $zone?->getVenueId() ?? 0;
    }
}
