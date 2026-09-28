<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Speaker;

use HiEvents\DomainObjects\Generated\SpeakerDomainObjectAbstract;
use HiEvents\DomainObjects\SpeakerDomainObject;
use HiEvents\Repository\Interfaces\SpeakerRepositoryInterface;
use Illuminate\Support\Str;

class CreateSpeakerHandler
{
    public function __construct(
        private readonly SpeakerRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $eventId, array $attributes): SpeakerDomainObject
    {
        return $this->repository->create(array_merge($attributes, [
            SpeakerDomainObjectAbstract::SHORT_ID => 'sp_'.Str::lower(Str::random(20)),
            SpeakerDomainObjectAbstract::EVENT_ID => $eventId,
        ]));
    }
}
