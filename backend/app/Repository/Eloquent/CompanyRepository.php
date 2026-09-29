<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\CompanyDomainObject;
use HiEvents\Models\Company;
use HiEvents\Repository\Interfaces\CompanyRepositoryInterface;

/**
 * @extends BaseRepository<CompanyDomainObject>
 */
class CompanyRepository extends BaseRepository implements CompanyRepositoryInterface
{
    protected function getModel(): string
    {
        return Company::class;
    }

    public function getDomainObject(): string
    {
        return CompanyDomainObject::class;
    }
}
