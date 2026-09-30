<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects${n}DomainObject;
use HiEvents\Models${n};
use HiEvents\Repository\Interfaces${n}RepositoryInterface;

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
