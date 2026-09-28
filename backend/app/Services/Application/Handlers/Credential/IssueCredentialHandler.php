<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Credential;

use HiEvents\DomainObjects\AccreditationDomainObject;
use HiEvents\DomainObjects\CredentialDomainObject;
use HiEvents\DomainObjects\Generated\AccreditationDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AccreditationStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AccreditationRepositoryInterface;
use HiEvents\Services\Domain\Credential\CredentialIssuanceService;

class IssueCredentialHandler
{
    public function __construct(
        private readonly CredentialIssuanceService $credentialIssuanceService,
        private readonly AccreditationRepositoryInterface $accreditationRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, array $attributes): CredentialDomainObject
    {
        if (! empty($attributes['accreditation_id'])) {
            return $this->issueForAccreditation($eventId, (int) $attributes['accreditation_id']);
        }

        return $this->credentialIssuanceService->issueForAttendee(
            eventId: $eventId,
            attendeeId: (int) $attributes['attendee_id'],
            personId: isset($attributes['person_id']) ? (int) $attributes['person_id'] : null,
        );
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function issueForAccreditation(int $eventId, int $accreditationId): CredentialDomainObject
    {
        /** @var AccreditationDomainObject|null $accreditation */
        $accreditation = $this->accreditationRepository->findFirstWhere([
            AccreditationDomainObjectAbstract::ID => $accreditationId,
            AccreditationDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($accreditation === null) {
            throw new ResourceNotFoundException(__('The accreditation could not be found.'));
        }

        // Issuing against an unapproved application would bypass the whole review step.
        if ($accreditation->getStatus() !== AccreditationStatus::APPROVED->value) {
            throw new ResourceConflictException(
                __('A credential can only be issued for an approved accreditation.')
            );
        }

        $approvedZones = $accreditation->getApprovedZones();

        return $this->credentialIssuanceService->issueForAccreditation(
            eventId: $eventId,
            accreditationId: $accreditationId,
            personId: $accreditation->getPersonId(),
            credentialType: 'ACCREDITED',
            accreditationTypeId: $accreditation->getAccreditationTypeId(),
            approvedZoneIds: is_array($approvedZones) ? array_map('intval', $approvedZones) : null,
        );
    }
}
