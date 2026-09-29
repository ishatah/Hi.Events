<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Session;

use HiEvents\DomainObjects\Generated\SessionRegistrationDomainObjectAbstract;
use HiEvents\Repository\Interfaces\SessionRegistrationRepositoryInterface;
use Illuminate\Support\Collection;

class GetSessionRegistrationsHandler
{
    public function __construct(
        private readonly SessionRegistrationRepositoryInterface $repository,
    ) {}

    public function handle(int $sessionId): Collection
    {
        return $this->repository->findWhere([
            SessionRegistrationDomainObjectAbstract::SESSION_ID => $sessionId,
        ]);
    }
}
