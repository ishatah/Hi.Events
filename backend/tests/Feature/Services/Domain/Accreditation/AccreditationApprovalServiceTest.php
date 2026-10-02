<?php

namespace Tests\Feature\Services\Domain\Accreditation;

use HiEvents\DomainObjects\Status\AccreditationStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Accreditation\AccreditationApprovalService;
use HiEvents\Services\Domain\Credential\CredentialIssuanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccreditationApprovalServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AccreditationApprovalService $service;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $venueId;

    private int $typeId;

    private int $personId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AccreditationApprovalService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;

        $this->eventId = $this->makeEvent();
        $this->venueId = $this->makeVenue();
        $this->typeId = $this->makeType();
        $this->personId = $this->makePerson();
    }

    public function test_an_application_can_be_submitted_and_approved(): void
    {
        $id = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );

        $this->assertSame(
            AccreditationStatus::SUBMITTED->value,
            DB::table('accreditations')->where('id', $id)->value('status')
        );

        $this->service->approve($id, $this->userId);

        $this->assertSame(
            AccreditationStatus::APPROVED->value,
            DB::table('accreditations')->where('id', $id)->value('status')
        );
    }

    public function test_an_issued_credential_records_who_issued_it(): void
    {
        $credentialId = $this->approveAndIssue();

        $this->assertSame(
            $this->userId,
            (int) DB::table('credentials')->where('id', $credentialId)->value('issued_by'),
            'A credential grants physical access, so the row has to say which operator '
            .'granted it; issued_by was never written.'
        );
    }

    public function test_revoking_a_credential_removes_its_access_grants(): void
    {
        $credentialId = $this->approveAndIssue();

        $this->assertGreaterThan(
            0,
            DB::table('access_grants')->where('credential_id', $credentialId)->count(),
            'Expected issuance to materialise at least one grant.'
        );

        app(CredentialIssuanceService::class)->revoke($credentialId, $this->userId, 'Lost badge');

        $this->assertSame(
            0,
            DB::table('access_grants')->where('credential_id', $credentialId)->count(),
            'Grants are the snapshot an offline reader carries, so leaving them active after '
            .'revocation keeps a revoked badge usable wherever that snapshot is trusted.'
        );
    }

    public function test_a_second_revocation_keeps_the_first_record(): void
    {
        $credentialId = $this->approveAndIssue();

        $service = app(CredentialIssuanceService::class);

        $service->revoke($credentialId, $this->userId, 'Lost badge');

        $first = DB::table('credentials')->where('id', $credentialId)->first();

        $otherUserId = (int) User::factory()->withAccount()->create()->id;

        $service->revoke($credentialId, $otherUserId, 'Different reason entirely');

        $second = DB::table('credentials')->where('id', $credentialId)->first();

        $this->assertSame(
            (int) $first->revoked_by,
            (int) $second->revoked_by,
            'Who revoked a credential is an accountability record, so a later call must not '
            .'overwrite it.'
        );

        $this->assertSame($first->revocation_reason, $second->revocation_reason);
        $this->assertSame($first->revoked_at, $second->revoked_at);
    }

    private function approveAndIssue(): int
    {
        $zoneId = $this->makeZone('REVOKEHALL');

        DB::table('accreditation_type_rules')->insert([
            'accreditation_type_id' => $this->typeId,
            'zone_id' => $zoneId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accreditationId = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            requestedZoneIds: [$zoneId],
            actorUserId: $this->userId,
        );

        $this->service->approve($accreditationId, $this->userId, approvedZoneIds: [$zoneId]);

        return $this->service->issueCredential($accreditationId, $this->userId);
    }

    public function test_every_transition_is_audited(): void
    {
        $id = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );

        $this->service->approve($id, $this->userId, notes: 'Verified press card');

        $trail = DB::table('accreditation_audit_logs')
            ->where('accreditation_id', $id)
            ->orderBy('occurred_at')
            ->get();

        $this->assertCount(2, $trail);
        $this->assertNull($trail[0]->from_status);
        $this->assertSame(AccreditationStatus::SUBMITTED->value, $trail[0]->to_status);
        $this->assertSame(AccreditationStatus::SUBMITTED->value, $trail[1]->from_status);
        $this->assertSame(AccreditationStatus::APPROVED->value, $trail[1]->to_status);
        $this->assertSame($this->userId, (int) $trail[1]->actor_user_id);
        $this->assertSame('Verified press card', $trail[1]->reason);
    }

    public function test_a_rejection_records_its_reason(): void
    {
        $id = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );

        $this->service->reject($id, $this->userId, 'No accreditation for this outlet.');

        $row = DB::table('accreditations')->where('id', $id)->first();

        $this->assertSame(AccreditationStatus::REJECTED->value, $row->status);
        $this->assertSame('No accreditation for this outlet.', $row->rejection_reason);
        $this->assertSame(
            'No accreditation for this outlet.',
            DB::table('accreditation_audit_logs')
                ->where('accreditation_id', $id)
                ->where('to_status', AccreditationStatus::REJECTED->value)
                ->value('reason')
        );
    }

    public function test_a_rejection_without_a_reason_is_refused(): void
    {
        $id = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );

        $this->expectExceptionMessage('A rejection reason is required.');
        $this->service->reject($id, $this->userId, '   ');
    }

    public function test_a_decided_application_cannot_be_decided_again(): void
    {
        $id = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );

        $this->service->approve($id, $this->userId);

        $this->expectException(ResourceConflictException::class);
        $this->service->approve($id, $this->userId);
    }

    public function test_a_rejected_application_cannot_be_approved_by_a_second_click(): void
    {
        $id = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );

        $this->service->reject($id, $this->userId, 'Not eligible.');

        $this->expectException(ResourceConflictException::class);
        $this->service->approve($id, $this->userId);
    }

    public function test_an_approver_may_grant_fewer_zones_than_were_requested(): void
    {
        $hall = $this->makeZone('HALL');
        $backstage = $this->makeZone('BACKSTAGE');

        $id = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            requestedZoneIds: [$hall, $backstage],
            actorUserId: $this->userId,
        );

        $this->service->approve($id, $this->userId, approvedZoneIds: [$hall]);

        $row = DB::table('accreditations')->where('id', $id)->first();

        $this->assertSame([$hall], json_decode((string) $row->approved_zones, true));
        $this->assertSame([$hall, $backstage], json_decode((string) $row->requested_zones, true));
    }

    public function test_a_type_that_does_not_require_approval_is_approved_on_submission(): void
    {
        $autoTypeId = $this->makeType(['requires_approval' => false, 'code' => 'AUTO']);
        $hall = $this->makeZone('AUTOHALL');

        $id = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $autoTypeId,
            requestedZoneIds: [$hall],
            actorUserId: $this->userId,
        );

        $row = DB::table('accreditations')->where('id', $id)->first();

        $this->assertSame(AccreditationStatus::APPROVED->value, $row->status);
        $this->assertSame([$hall], json_decode((string) $row->approved_zones, true));
        $this->assertNotNull($row->reviewed_at);
    }

    public function test_a_duplicate_application_is_refused(): void
    {
        $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );

        $this->expectExceptionMessage('An application for this accreditation type already exists.');
        $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );
    }

    public function test_reapplying_after_a_rejection_is_allowed(): void
    {
        $first = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );

        $this->service->reject($first, $this->userId, 'Missing documents.');

        $second = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );

        $this->assertNotSame($first, $second);
    }

    public function test_a_credential_is_issued_only_for_an_approved_application(): void
    {
        $id = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );

        $this->expectExceptionMessage('Only an approved accreditation can be issued a credential.');
        $this->service->issueCredential($id, $this->userId);
    }

    public function test_an_approved_application_yields_a_credential_of_the_type_code(): void
    {
        $id = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );

        $this->service->approve($id, $this->userId);
        $credentialId = $this->service->issueCredential($id, $this->userId);

        $credential = DB::table('credentials')->where('id', $credentialId)->first();

        $this->assertSame('MEDIA', $credential->credential_type);
        $this->assertSame($id, (int) $credential->accreditation_id);
        $this->assertSame('ACTIVE', $credential->status);
    }

    public function test_a_second_credential_is_refused_while_one_is_active(): void
    {
        $id = $this->service->submit(
            eventId: $this->eventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );

        $this->service->approve($id, $this->userId);
        $this->service->issueCredential($id, $this->userId);

        $this->expectExceptionMessage('This accreditation already has an active credential.');
        $this->service->issueCredential($id, $this->userId);
    }

    public function test_an_accreditation_type_from_another_event_is_refused(): void
    {
        $otherEventId = $this->makeEvent();

        $this->expectExceptionMessage('The accreditation type could not be found.');
        $this->service->submit(
            eventId: $otherEventId,
            personId: $this->personId,
            accreditationTypeId: $this->typeId,
            actorUserId: $this->userId,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeType(array $overrides = []): int
    {
        return (int) DB::table('accreditation_types')->insertGetId(array_merge([
            'short_id' => 'at_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'code' => 'MEDIA',
            'name' => 'Media',
            'requires_approval' => true,
            'requires_photo' => false,
            'requires_id_document' => false,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function makeZone(string $code): int
    {
        return (int) DB::table('zones')->insertGetId([
            'short_id' => 'zn_'.Str::lower(Str::random(20)),
            'venue_id' => $this->venueId,
            'name' => $code,
            'code' => $code,
            'zone_type' => 'GENERAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeVenue(): int
    {
        return (int) DB::table('venues')->insertGetId([
            'short_id' => 'vn_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Accreditation Venue',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePerson(): int
    {
        return (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Press',
            'last_name' => 'Person',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Accreditation Organizer',
            'email' => 'acc-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Accreditation Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(5),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
