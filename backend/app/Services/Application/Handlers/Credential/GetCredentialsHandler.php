<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Credential;

use HiEvents\DomainObjects\Generated\CredentialDomainObjectAbstract;
use HiEvents\Repository\Interfaces\CredentialRepositoryInterface;
use Illuminate\Support\Collection;

class GetCredentialsHandler
{
    public function __construct(
        private readonly CredentialRepositoryInterface $credentialRepository,
    ) {}

    public function handle(int $eventId): Collection
    {
        return $this->credentialRepository->findWhere([
            CredentialDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }
}
