<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccessRule;

use HiEvents\DomainObjects\Generated\AccessRuleDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AccessRuleRepositoryInterface;
use Illuminate\Support\Collection;

class GetAccessRulesHandler
{
    public function __construct(
        private readonly AccessRuleRepositoryInterface $repository,
    ) {}

    public function handle(int $eventId): Collection
    {
        return $this->repository->findWhere([
            AccessRuleDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }
}
