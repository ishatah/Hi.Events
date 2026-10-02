<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Credential;

use HiEvents\DomainObjects\CredentialDomainObject;
use HiEvents\DomainObjects\Generated\CredentialDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CredentialStatus;
use HiEvents\Repository\Interfaces\CredentialRepositoryInterface;
use HiEvents\Services\Domain\Access\GrantMaterializationService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Issues the right of access.
 *
 * A credential comes from exactly one source — an approved accreditation, or a paid
 * attendee — which the credentials_exactly_one_source CHECK constraint enforces. That is
 * what lets one access engine serve ticket buyers, accredited press and staff without
 * parallel code paths.
 *
 * @see docs/arzo-master-plan/23-accreditation.md
 */
class CredentialIssuanceService
{
    public function __construct(
        private readonly CredentialRepositoryInterface $credentialRepository,
        private readonly GrantMaterializationService $grantMaterializationService,
        private readonly CredentialIdentifierService $identifierService,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function issueForAttendee(int $eventId, int $attendeeId, ?int $personId = null): CredentialDomainObject
    {
        $productId = $this->databaseManager->table('attendees')
            ->where('id', $attendeeId)
            ->value('product_id');

        return $this->issue($eventId, [
            CredentialDomainObjectAbstract::ATTENDEE_ID => $attendeeId,
            CredentialDomainObjectAbstract::PERSON_ID => $personId,
            CredentialDomainObjectAbstract::CREDENTIAL_TYPE => 'ATTENDEE',
        ], subjects: $productId !== null ? ['PRODUCT' => (int) $productId] : []);
    }

    /**
     * @param  array<int, int>|null  $approvedZoneIds
     */
    public function issueForAccreditation(
        int $eventId,
        int $accreditationId,
        int $personId,
        string $credentialType,
        ?int $accreditationTypeId = null,
        ?array $approvedZoneIds = null,
        ?int $issuedByUserId = null,
    ): CredentialDomainObject {
        return $this->issue($eventId, [
            CredentialDomainObjectAbstract::ACCREDITATION_ID => $accreditationId,
            CredentialDomainObjectAbstract::PERSON_ID => $personId,
            CredentialDomainObjectAbstract::CREDENTIAL_TYPE => $credentialType,
            CredentialDomainObjectAbstract::ISSUED_BY => $issuedByUserId,
        ], $accreditationTypeId, $approvedZoneIds);
    }

    /**
     * Revoking twice keeps the first revocation: who revoked the credential and why is an
     * accountability record, and the grants are already gone.
     */
    public function revoke(int $credentialId, ?int $revokedByUserId, string $reason): void
    {
        $this->databaseManager->transaction(function () use ($credentialId, $revokedByUserId, $reason): void {
            $updated = $this->credentialRepository->updateWhere(
                attributes: [
                    CredentialDomainObjectAbstract::STATUS => CredentialStatus::REVOKED->value,
                    CredentialDomainObjectAbstract::REVOKED_AT => now()->toDateTimeString(),
                    CredentialDomainObjectAbstract::REVOKED_BY => $revokedByUserId,
                    CredentialDomainObjectAbstract::REVOCATION_REASON => $reason,
                ],
                where: [
                    CredentialDomainObjectAbstract::ID => $credentialId,
                    [CredentialDomainObjectAbstract::REVOKED_AT, 'null', null],
                ],
            );

            if ($updated === 0) {
                return;
            }

            $this->grantMaterializationService->revokeExisting($credentialId);
        });
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, int>|null  $approvedZoneIds
     * @param  array<string, int|null>  $subjects
     */
    private function issue(
        int $eventId,
        array $source,
        ?int $accreditationTypeId = null,
        ?array $approvedZoneIds = null,
        array $subjects = [],
    ): CredentialDomainObject {
        return $this->databaseManager->transaction(function () use ($eventId, $source, $accreditationTypeId, $approvedZoneIds, $subjects) {
            $identifier = $this->identifierService->generate();

            $credential = $this->credentialRepository->create(array_merge([
                CredentialDomainObjectAbstract::SHORT_ID => 'cr_'.Str::lower(Str::random(20)),
                CredentialDomainObjectAbstract::EVENT_ID => $eventId,
                CredentialDomainObjectAbstract::STATUS => CredentialStatus::ACTIVE->value,
                CredentialDomainObjectAbstract::IDENTIFIER => $identifier,
                CredentialDomainObjectAbstract::IDENTIFIER_HASH => $this->identifierService->hash($identifier),
                CredentialDomainObjectAbstract::ISSUED_AT => now()->toDateTimeString(),
            ], array_filter($source, static fn ($value): bool => $value !== null)));

            $this->grantMaterializationService->materialize(
                credentialId: $credential->getId(),
                eventId: $eventId,
                accreditationTypeId: $accreditationTypeId,
                approvedZoneIds: $approvedZoneIds,
                subjects: $subjects,
            );

            return $credential;
        });
    }
}
