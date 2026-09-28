<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccessRule;

use HiEvents\DomainObjects\AccessRuleDomainObject;
use HiEvents\DomainObjects\Generated\AccessRuleDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AccessRuleRepositoryInterface;
use Illuminate\Support\Str;

class CreateAccessRuleHandler
{
    public function __construct(
        private readonly AccessRuleRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $eventId, array $attributes): AccessRuleDomainObject
    {
        return $this->repository->create(array_merge($attributes, [
            AccessRuleDomainObjectAbstract::SHORT_ID => 'ar_'.Str::lower(Str::random(20)),
            AccessRuleDomainObjectAbstract::EVENT_ID => $eventId,
        ]));
    }
}
