<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccreditationType;

use HiEvents\DomainObjects\AccreditationTypeDomainObject;
use HiEvents\DomainObjects\Generated\AccreditationTypeDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AccreditationTypeRepositoryInterface;
use Illuminate\Support\Str;

class CreateAccreditationTypeHandler
{
    public function __construct(
        private readonly AccreditationTypeRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $eventId, array $attributes): AccreditationTypeDomainObject
    {
        return $this->repository->create(array_merge($attributes, [
            AccreditationTypeDomainObjectAbstract::SHORT_ID => 'at_'.Str::lower(Str::random(20)),
            AccreditationTypeDomainObjectAbstract::EVENT_ID => $eventId,
        ]));
    }
}
