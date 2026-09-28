<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccessRule;

use HiEvents\DomainObjects\AccessRuleDomainObject;
use HiEvents\DomainObjects\Generated\AccessRuleDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AccessRuleRepositoryInterface;

class GetAccessRuleHandler
{
    public function __construct(
        private readonly AccessRuleRepositoryInterface $repository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $id): AccessRuleDomainObject
    {
        $record = $this->repository->findFirstWhere([
            AccessRuleDomainObjectAbstract::ID => $id,
            AccessRuleDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($record === null) {
            throw new ResourceNotFoundException(__('The requested record could not be found.'));
        }

        return $record;
    }
}
