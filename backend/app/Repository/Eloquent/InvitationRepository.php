<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\InvitationDomainObject;
use HiEvents\Models\Invitation;
use HiEvents\Repository\Interfaces\InvitationRepositoryInterface;

/**
 * @extends BaseRepository<InvitationDomainObject>
 */
class InvitationRepository extends BaseRepository implements InvitationRepositoryInterface
{
    protected function getModel(): string
    {
        return Invitation::class;
    }

    public function getDomainObject(): string
    {
        return InvitationDomainObject::class;
    }
}
