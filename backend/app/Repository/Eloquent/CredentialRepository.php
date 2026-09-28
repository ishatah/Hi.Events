<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\CredentialDomainObject;
use HiEvents\Models\Credential;
use HiEvents\Repository\Interfaces\CredentialRepositoryInterface;

/**
 * @extends BaseRepository<CredentialDomainObject>
 */
class CredentialRepository extends BaseRepository implements CredentialRepositoryInterface
{
    protected function getModel(): string
    {
        return Credential::class;
    }

    public function getDomainObject(): string
    {
        return CredentialDomainObject::class;
    }
}
