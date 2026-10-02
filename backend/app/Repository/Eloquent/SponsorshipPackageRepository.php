<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SponsorshipPackageDomainObject;
use HiEvents\Models\SponsorshipPackage;
use HiEvents\Repository\Interfaces\SponsorshipPackageRepositoryInterface;

/**
 * @extends BaseRepository<SponsorshipPackageDomainObject>
 */
class SponsorshipPackageRepository extends BaseRepository implements SponsorshipPackageRepositoryInterface
{
    protected function getModel(): string
    {
        return SponsorshipPackage::class;
    }

    public function getDomainObject(): string
    {
        return SponsorshipPackageDomainObject::class;
    }
}
