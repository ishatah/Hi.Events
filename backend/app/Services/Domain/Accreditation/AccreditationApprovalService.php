<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Accreditation;

use HiEvents\DomainObjects\Status\AccreditationStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Credential\CredentialIssuanceService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Moves an accreditation application through review.
 *
 * Every transition is written to accreditation_audit_logs before the row changes.
 * Accreditation decisions are contestable — a rejected journalist may escalate — so who
 * decided what, when, and why is a requirement rather than a convenience.
 *
 * @see docs/arzo-master-plan/23-accreditation.md
 */
class AccreditationApprovalService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly CredentialIssuanceService $credentialIssuanceService,
    ) {}

    /**
     * @param  array<int, int>|null  $requestedZoneIds
     *
     * @throws ResourceConflictException
     */
    public function submit(
        int $eventId,
        int $personId,
        int $accreditationTypeId,
        ?array $requestedZoneIds = null,
        ?array $formData = null,
        ?int $actorUserId = null,
    ): int {
        $type = $this->databaseManager->table('accreditation_types')
            ->where('id', $accreditationTypeId)
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->first();

        if ($type === null) {
            throw new ResourceConflictException(__('The accreditation type could not be found.'));
        }

        $existing = $this->databaseManager->table('accreditations')
            ->where('event_id', $eventId)
            ->where('person_id', $personId)
            ->where('accreditation_type_id', $accreditationTypeId)
            ->whereNull('deleted_at')
            ->first();

        if ($existing !== null && ! $this->isReapplicable((string) $existing->status)) {
            throw new ResourceConflictException(
                __('An application for this accreditation type already exists.')
            );
        }

        return $this->databaseManager->transaction(function () use (
            $eventId,
            $personId,
            $accreditationTypeId,
            $requestedZoneIds,
            $formData,
            $actorUserId,
            $type,
            $existing
        ): int {
            $status = $type->requires_approval
                ? AccreditationStatus::SUBMITTED
                : AccreditationStatus::APPROVED;

            // One live application per person per type is enforced by a partial unique
            // index. A rejected or withdrawn applicant must still be able to reapply —
            // usually the same person returning with the documents they were missing — so
            // the closed application is archived rather than blocking them forever. It
            // stays readable through its audit trail.
            if ($existing !== null) {
                $this->databaseManager->table('accreditations')
                    ->where('id', $existing->id)
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
            }

            $accreditationId = (int) $this->databaseManager->table('accreditations')->insertGetId([
                'short_id' => 'ac_'.Str::lower(Str::random(20)),
                'event_id' => $eventId,
                'person_id' => $personId,
                'accreditation_type_id' => $accreditationTypeId,
                'status' => $status->value,
                'submitted_at' => now(),
                'requested_zones' => $requestedZoneIds !== null
                    ? json_encode(array_values($requestedZoneIds))
                    : null,
                // An auto-approved type grants exactly what was asked for; there is no
                // reviewer to narrow it.
                'approved_zones' => ! $type->requires_approval && $requestedZoneIds !== null
                    ? json_encode(array_values($requestedZoneIds))
                    : null,
                'form_data' => $formData !== null ? json_encode($formData) : null,
                'reviewed_at' => ! $type->requires_approval ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit($accreditationId, null, $status, $actorUserId);

            return $accreditationId;
        });
    }

    /**
     * @param  array<int, int>|null  $approvedZoneIds  Narrower than requested is the normal
     *                                                 case: press ask for backstage and are
     *                                                 given the hall.
     *
     * @throws ResourceConflictException
     */
    public function approve(
        int $accreditationId,
        ?int $actorUserId,
        ?array $approvedZoneIds = null,
        ?string $notes = null,
    ): void {
        $this->databaseManager->transaction(function () use (
            $accreditationId,
            $actorUserId,
            $approvedZoneIds,
            $notes
        ): void {
            $accreditation = $this->lockAndLoad($accreditationId);
            $from = AccreditationStatus::tryFrom((string) $accreditation->status);

            $this->guardTransition($from, AccreditationStatus::APPROVED);

            $this->databaseManager->table('accreditations')
                ->where('id', $accreditationId)
                ->update([
                    'status' => AccreditationStatus::APPROVED->value,
                    'reviewed_at' => now(),
                    'reviewed_by' => $actorUserId,
                    'internal_notes' => $notes,
                    'approved_zones' => $approvedZoneIds !== null
                        ? json_encode(array_values($approvedZoneIds))
                        : $accreditation->requested_zones,
                    'rejection_reason' => null,
                    'updated_at' => now(),
                ]);

            $this->audit($accreditationId, $from, AccreditationStatus::APPROVED, $actorUserId, $notes);
        });
    }

    /**
     * @throws ResourceConflictException
     */
    public function reject(int $accreditationId, ?int $actorUserId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new ResourceConflictException(__('A rejection reason is required.'));
        }

        $this->databaseManager->transaction(function () use ($accreditationId, $actorUserId, $reason): void {
            $accreditation = $this->lockAndLoad($accreditationId);
            $from = AccreditationStatus::tryFrom((string) $accreditation->status);

            $this->guardTransition($from, AccreditationStatus::REJECTED);

            $this->databaseManager->table('accreditations')
                ->where('id', $accreditationId)
                ->update([
                    'status' => AccreditationStatus::REJECTED->value,
                    'reviewed_at' => now(),
                    'reviewed_by' => $actorUserId,
                    'rejection_reason' => $reason,
                    'updated_at' => now(),
                ]);

            $this->audit($accreditationId, $from, AccreditationStatus::REJECTED, $actorUserId, $reason);
        });
    }

    /**
     * Issues the credential an approved accreditation entitles its holder to.
     *
     * Kept separate from approve() because approval is a decision and issuance is an act
     * that happens at a desk, often later and by somebody else.
     *
     * @throws ResourceConflictException
     */
    public function issueCredential(int $accreditationId, ?int $actorUserId): int
    {
        $accreditation = $this->databaseManager->table('accreditations')
            ->where('id', $accreditationId)
            ->whereNull('deleted_at')
            ->first();

        if ($accreditation === null) {
            throw new ResourceConflictException(__('The accreditation could not be found.'));
        }

        if ($accreditation->status !== AccreditationStatus::APPROVED->value) {
            throw new ResourceConflictException(
                __('Only an approved accreditation can be issued a credential.')
            );
        }

        $existing = $this->databaseManager->table('credentials')
            ->where('accreditation_id', $accreditationId)
            ->whereNotIn('status', ['REVOKED', 'EXPIRED'])
            ->first();

        if ($existing !== null) {
            throw new ResourceConflictException(
                __('This accreditation already has an active credential.')
            );
        }

        $type = $this->databaseManager->table('accreditation_types')
            ->where('id', $accreditation->accreditation_type_id)
            ->first();

        $credential = $this->credentialIssuanceService->issueForAccreditation(
            eventId: (int) $accreditation->event_id,
            accreditationId: $accreditationId,
            personId: (int) $accreditation->person_id,
            // The credential's type mirrors the accreditation type's code, so a MEDIA
            // application yields a MEDIA credential rather than a generic one.
            credentialType: (string) $type->code,
            accreditationTypeId: (int) $accreditation->accreditation_type_id,
            approvedZoneIds: $this->parseIntArray($accreditation->approved_zones),
            issuedByUserId: $actorUserId,
        );

        return $credential->getId();
    }

    /**
     * A closed application does not block a fresh one. An approved or still-open
     * application does, because two live entitlements for the same person and type is the
     * duplicate this guards against.
     */
    private function isReapplicable(string $status): bool
    {
        return in_array($status, [
            AccreditationStatus::REJECTED->value,
            AccreditationStatus::WITHDRAWN->value,
            AccreditationStatus::EXPIRED->value,
        ], true);
    }

    /**
     * @throws ResourceConflictException
     */
    private function lockAndLoad(int $accreditationId): object
    {
        $accreditation = $this->databaseManager->table('accreditations')
            ->where('id', $accreditationId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if ($accreditation === null) {
            throw new ResourceConflictException(__('The accreditation could not be found.'));
        }

        return $accreditation;
    }

    /**
     * A decided application is not re-decided silently. Reversing a rejection is a
     * deliberate act that belongs in its own flow with its own audit entry, not an
     * accidental second click.
     *
     * @throws ResourceConflictException
     */
    private function guardTransition(?AccreditationStatus $from, AccreditationStatus $to): void
    {
        $allowed = [
            AccreditationStatus::SUBMITTED->value,
            AccreditationStatus::UNDER_REVIEW->value,
        ];

        if ($from === null || ! in_array($from->value, $allowed, true)) {
            throw new ResourceConflictException(sprintf(
                '%s %s',
                __('This accreditation has already been decided.'),
                __('Current status:').' '.($from?->value ?? 'unknown')
            ));
        }

        if ($from === $to) {
            throw new ResourceConflictException(__('This accreditation has already been decided.'));
        }
    }

    private function audit(
        int $accreditationId,
        ?AccreditationStatus $from,
        AccreditationStatus $to,
        ?int $actorUserId,
        ?string $reason = null,
    ): void {
        $this->databaseManager->table('accreditation_audit_logs')->insert([
            'short_id' => 'aa_'.Str::lower(Str::random(20)),
            'accreditation_id' => $accreditationId,
            'actor_user_id' => $actorUserId,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'reason' => $reason,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The zone columns are jsonb rather than the bigint[] the plan sketched, so they hold
     * a JSON array.
     *
     * @return array<int, int>|null
     */
    private function parseIntArray(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        return array_map('intval', $decoded);
    }
}
