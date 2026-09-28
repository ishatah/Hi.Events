<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccessRule;

use HiEvents\DomainObjects\AccessRuleDomainObject;
use HiEvents\DomainObjects\Generated\AccessRuleDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AccessRuleRepositoryInterface;

class UpdateAccessRuleHandler
{
    public function __construct(
        private readonly AccessRuleRepositoryInterface $repository,
        private readonly GetAccessRuleHandler $getAccessRuleHandler,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, int $id, array $attributes): AccessRuleDomainObject
    {
        // Scoped read first, so an id belonging to another tenant cannot be updated.
        $this->getAccessRuleHandler->handle($eventId, $id);

        $this->repository->updateWhere(
            attributes: $attributes,
            where: [
                AccessRuleDomainObjectAbstract::ID => $id,
                AccessRuleDomainObjectAbstract::EVENT_ID => $eventId,
            ],
        );

        return $this->getAccessRuleHandler->handle($eventId, $id);
    }
}
