<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\DeviceDomainObject;
use HiEvents\Models\Device;
use HiEvents\Repository\Interfaces\DeviceRepositoryInterface;

/**
 * @extends BaseRepository<DeviceDomainObject>
 */
class DeviceRepository extends BaseRepository implements DeviceRepositoryInterface
{
    protected function getModel(): string
    {
        return Device::class;
    }

    public function getDomainObject(): string
    {
        return DeviceDomainObject::class;
    }
}
