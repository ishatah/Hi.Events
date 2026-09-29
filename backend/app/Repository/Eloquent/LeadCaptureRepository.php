<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\LeadCaptureDomainObject;
use HiEvents\Models\LeadCapture;
use HiEvents\Repository\Interfaces\LeadCaptureRepositoryInterface;

/**
 * @extends BaseRepository<LeadCaptureDomainObject>
 */
class LeadCaptureRepository extends BaseRepository implements LeadCaptureRepositoryInterface
{
    protected function getModel(): string
    {
        return LeadCapture::class;
    }

    public function getDomainObject(): string
    {
        return LeadCaptureDomainObject::class;
    }
}
