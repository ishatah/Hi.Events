<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SessionProductDomainObject;
use HiEvents\Models\SessionProduct;
use HiEvents\Repository\Interfaces\SessionProductRepositoryInterface;

/**
 * @extends BaseRepository<SessionProductDomainObject>
 */
class SessionProductRepository extends BaseRepository implements SessionProductRepositoryInterface
{
    protected function getModel(): string
    {
        return SessionProduct::class;
    }

    public function getDomainObject(): string
    {
        return SessionProductDomainObject::class;
    }
}
