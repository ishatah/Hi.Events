<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Session;

use HiEvents\DomainObjects\Generated\SessionDomainObjectAbstract;
use HiEvents\DomainObjects\SessionDomainObject;
use HiEvents\Repository\Interfaces\SessionRepositoryInterface;
use Illuminate\Support\Str;

class CreateSessionHandler
{
    public function __construct(
        private readonly SessionRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $eventId, array $attributes): SessionDomainObject
    {
        return $this->repository->create(array_merge($attributes, [
            SessionDomainObjectAbstract::SHORT_ID => 'ss_'.Str::lower(Str::random(20)),
            SessionDomainObjectAbstract::EVENT_ID => $eventId,
        ]));
    }
}
