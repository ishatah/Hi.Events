<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\ApiKeyDomainObject;
use HiEvents\Models\ApiKey;
use HiEvents\Repository\Interfaces\ApiKeyRepositoryInterface;

/**
 * @extends BaseRepository<ApiKeyDomainObject>
 */
class ApiKeyRepository extends BaseRepository implements ApiKeyRepositoryInterface
{
    protected function getModel(): string
    {
        return ApiKey::class;
    }

    public function getDomainObject(): string
    {
        return ApiKeyDomainObject::class;
    }
}
