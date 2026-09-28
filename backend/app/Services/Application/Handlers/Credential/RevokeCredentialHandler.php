<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Credential;

use HiEvents\DomainObjects\Generated\CredentialDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\CredentialRepositoryInterface;
use HiEvents\Services\Domain\Credential\CredentialIssuanceService;

class RevokeCredentialHandler
{
    public function __construct(
        private readonly CredentialIssuanceService $credentialIssuanceService,
        private readonly CredentialRepositoryInterface $credentialRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $credentialId, ?int $userId, string $reason): void
    {
        $credential = $this->credentialRepository->findFirstWhere([
            CredentialDomainObjectAbstract::ID => $credentialId,
            CredentialDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($credential === null) {
            throw new ResourceNotFoundException(__('The credential could not be found.'));
        }

        $this->credentialIssuanceService->revoke($credentialId, $userId, $reason);
    }
}
