<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Track;

use HiEvents\DomainObjects\Generated\TrackDomainObjectAbstract;
use HiEvents\DomainObjects\TrackDomainObject;
use HiEvents\Repository\Interfaces\TrackRepositoryInterface;
use Illuminate\Support\Str;

class CreateTrackHandler
{
    public function __construct(
        private readonly TrackRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $eventId, array $attributes): TrackDomainObject
    {
        return $this->repository->create(array_merge($attributes, [
            TrackDomainObjectAbstract::SHORT_ID => 'tr_'.Str::lower(Str::random(20)),
            TrackDomainObjectAbstract::EVENT_ID => $eventId,
        ]));
    }
}
