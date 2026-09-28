<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccessRule;

use HiEvents\DomainObjects\Generated\AccessRuleDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AccessRuleRepositoryInterface;

class DeleteAccessRuleHandler
{
    public function __construct(
        private readonly AccessRuleRepositoryInterface $repository,
        private readonly GetAccessRuleHandler $getAccessRuleHandler,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, int $id): void
    {
        $this->getAccessRuleHandler->handle($eventId, $id);

        $this->repository->deleteWhere([
            AccessRuleDomainObjectAbstract::ID => $id,
            AccessRuleDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }
}
