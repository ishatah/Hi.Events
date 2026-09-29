<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Exhibitor;

use HiEvents\DomainObjects\Status\AccreditationStatus;
use HiEvents\DomainObjects\Status\ExhibitorStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Accreditation\AccreditationApprovalService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Names and withdraws exhibitor staff.
 *
 * A staff pass is an EXHIBITOR accreditation rather than a third source on credentials.
 * That keeps the exactly-one-source CHECK as designed and reuses approval, audit, type
 * rules and max_issuable instead of teaching every credential consumer a new shape.
 *
 * Within quota the accreditation is auto-approved, because an exhibitor filling its
 * contracted allocation should not wait on a human. Beyond quota it is refused rather than
 * silently queued, so the exhibitor learns immediately that they need to buy more.
 *
 * @see docs/arzo-master-plan/32-exhibitor-management.md
 */
class ExhibitorStaffService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly AccreditationApprovalService $accreditationApprovalService,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function nameStaff(
        int $eventExhibitorId,
        int $personId,
        string $role = 'STAFF',
        ?int $actorUserId = null,
    ): int {
        return $this->databaseManager->transaction(function () use (
            $eventExhibitorId,
            $personId,
            $role,
            $actorUserId
        ): int {
            // Locked because the quota check and the insert must not interleave with another
            // admin naming somebody at the same moment, which would let both through.
            $exhibitor = $this->databaseManager->table('event_exhibitors')
                ->where('id', $eventExhibitorId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($exhibitor === null) {
                throw new ResourceConflictException(__('The exhibitor could not be found.'));
            }

            $status = ExhibitorStatus::tryFrom((string) $exhibitor->status);

            if ($status === null || ! $status->permitsStaffPasses()) {
                throw new ResourceConflictException(
                    __('Staff passes can only be issued once the exhibitor is contracted.')
                );
            }

            $this->guardPersonBelongsToAccount($personId, (int) $exhibitor->event_id);

            $existing = $this->databaseManager->table('exhibitor_staff')
                ->where('event_exhibitor_id', $eventExhibitorId)
                ->where('person_id', $personId)
                ->whereNull('deleted_at')
                ->first();

            if ($existing !== null) {
                throw new ResourceConflictException(
                    __('That person is already named as staff for this exhibitor.')
                );
            }

            $this->guardWithinQuota($exhibitor);

            $accreditationId = $this->requestAccreditation(
                eventId: (int) $exhibitor->event_id,
                personId: $personId,
                actorUserId: $actorUserId,
            );

            return (int) $this->databaseManager->table('exhibitor_staff')->insertGetId([
                'short_id' => 'es_'.Str::lower(Str::random(20)),
                'event_exhibitor_id' => $eventExhibitorId,
                'person_id' => $personId,
                'role' => $role,
                'accreditation_id' => $accreditationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Withdrawing staff also withdraws the accreditation behind the pass.
     *
     * Leaving it approved would keep a working credential in the hands of somebody the
     * exhibitor has removed.
     *
     * @throws ResourceConflictException
     */
    public function withdrawStaff(int $exhibitorStaffId): void
    {
        $this->databaseManager->transaction(function () use ($exhibitorStaffId): void {
            $staff = $this->databaseManager->table('exhibitor_staff')
                ->where('id', $exhibitorStaffId)
                ->whereNull('deleted_at')
                ->first();

            if ($staff === null) {
                throw new ResourceConflictException(__('That staff member could not be found.'));
            }

            $this->databaseManager->table('exhibitor_staff')
                ->where('id', $exhibitorStaffId)
                ->update(['deleted_at' => now(), 'updated_at' => now()]);

            if ($staff->accreditation_id === null) {
                return;
            }

            $this->databaseManager->table('accreditations')
                ->where('id', $staff->accreditation_id)
                ->update([
                    'status' => AccreditationStatus::WITHDRAWN->value,
                    'updated_at' => now(),
                ]);

            $this->databaseManager->table('credentials')
                ->where('accreditation_id', $staff->accreditation_id)
                ->whereNotIn('status', ['REVOKED', 'EXPIRED'])
                ->update([
                    'status' => 'REVOKED',
                    'revoked_at' => now(),
                    'revocation_reason' => __('Exhibitor staff withdrawn'),
                    'updated_at' => now(),
                ]);
        });
    }

    public function namedStaffCount(int $eventExhibitorId): int
    {
        return $this->databaseManager->table('exhibitor_staff')
            ->where('event_exhibitor_id', $eventExhibitorId)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * @throws ResourceConflictException
     */
    private function guardWithinQuota(object $exhibitor): void
    {
        if ($exhibitor->staff_pass_quota === null) {
            return;
        }

        $named = $this->namedStaffCount((int) $exhibitor->id);

        if ($named >= (int) $exhibitor->staff_pass_quota) {
            throw new ResourceConflictException(
                __('This exhibitor has used all :quota staff passes.', [
                    'quota' => (int) $exhibitor->staff_pass_quota,
                ])
            );
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function requestAccreditation(int $eventId, int $personId, ?int $actorUserId): ?int
    {
        $type = $this->databaseManager->table('accreditation_types')
            ->where('event_id', $eventId)
            ->where('code', 'EXHIBITOR')
            ->whereNull('deleted_at')
            ->first();

        // An event with no EXHIBITOR type yet still records the staff member; the pass
        // follows once the organizer defines the type. Refusing here would block an
        // exhibitor from building their roster before accreditation opens.
        if ($type === null) {
            return null;
        }

        return $this->accreditationApprovalService->submit(
            eventId: $eventId,
            personId: $personId,
            accreditationTypeId: (int) $type->id,
            actorUserId: $actorUserId,
        );
    }

    /**
     * @throws ResourceConflictException
     */
    private function guardPersonBelongsToAccount(int $personId, int $eventId): void
    {
        $accountId = $this->databaseManager->table('events')
            ->where('id', $eventId)
            ->value('account_id');

        $belongs = $this->databaseManager->table('persons')
            ->where('id', $personId)
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $belongs) {
            throw new ResourceConflictException(
                __('That person does not belong to this account.')
            );
        }
    }
}
