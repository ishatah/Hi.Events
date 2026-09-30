<?php

namespace Tests\Feature\Services\Domain\Exhibitor;

use HiEvents\DomainObjects\Enums\LeadCaptureResolution;
use HiEvents\DomainObjects\Status\ExhibitorStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Credential\CredentialIdentifierService;
use HiEvents\Services\Domain\Exhibitor\BoothAssignmentService;
use HiEvents\Services\Domain\Exhibitor\ExhibitorStaffService;
use HiEvents\Services\Domain\Exhibitor\LeadCaptureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExhibitorSubsystemTest extends TestCase
{
    use DatabaseTransactions;

    private ExhibitorStaffService $staff;

    private BoothAssignmentService $booths;

    private LeadCaptureService $leads;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $venueId;

    private int $companyId;

    private int $exhibitorId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = app(ExhibitorStaffService::class);
        $this->booths = app(BoothAssignmentService::class);
        $this->leads = app(LeadCaptureService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;

        $this->eventId = $this->makeEvent();
        $this->venueId = $this->makeVenue();
        DB::table('event_venues')->insert([
            'event_id' => $this->eventId,
            'venue_id' => $this->venueId,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->companyId = $this->makeCompany();
        $this->exhibitorId = $this->makeExhibitor(ExhibitorStatus::CONTRACTED, staffQuota: 2);
    }

    // ---------------------------------------------------------------- staff passes

    public function test_naming_staff_creates_an_exhibitor_accreditation(): void
    {
        $this->makeExhibitorAccreditationType();
        $personId = $this->makePerson();

        $staffId = $this->staff->nameStaff($this->exhibitorId, $personId, actorUserId: $this->userId);

        $row = DB::table('exhibitor_staff')->where('id', $staffId)->first();

        $this->assertNotNull($row->accreditation_id);
        $this->assertSame(
            'EXHIBITOR',
            DB::table('accreditations')
                ->join('accreditation_types', 'accreditation_types.id', '=', 'accreditations.accreditation_type_id')
                ->where('accreditations.id', $row->accreditation_id)
                ->value('accreditation_types.code'),
            'A staff pass must come through accreditation rather than a new credential source.'
        );
    }

    public function test_staff_can_be_named_before_an_exhibitor_type_exists(): void
    {
        $staffId = $this->staff->nameStaff($this->exhibitorId, $this->makePerson());

        $this->assertNull(
            DB::table('exhibitor_staff')->where('id', $staffId)->value('accreditation_id'),
            'An exhibitor must be able to build a roster before accreditation opens.'
        );
    }

    public function test_the_staff_quota_is_enforced(): void
    {
        $this->staff->nameStaff($this->exhibitorId, $this->makePerson());
        $this->staff->nameStaff($this->exhibitorId, $this->makePerson());

        $this->expectExceptionMessageMatches('/used all 2 staff passes/');
        $this->staff->nameStaff($this->exhibitorId, $this->makePerson());
    }

    public function test_an_unlimited_quota_never_blocks(): void
    {
        $unlimited = $this->makeExhibitor(ExhibitorStatus::ACTIVE, staffQuota: null);

        for ($i = 0; $i < 4; $i++) {
            $this->staff->nameStaff($unlimited, $this->makePerson());
        }

        $this->assertSame(4, $this->staff->namedStaffCount($unlimited));
    }

    public function test_staff_cannot_be_named_before_the_exhibitor_is_contracted(): void
    {
        $applied = $this->makeExhibitor(ExhibitorStatus::APPLIED, staffQuota: 5);

        $this->expectExceptionMessageMatches('/once the exhibitor is contracted/');
        $this->staff->nameStaff($applied, $this->makePerson());
    }

    public function test_the_same_person_cannot_be_named_twice(): void
    {
        $personId = $this->makePerson();
        $this->staff->nameStaff($this->exhibitorId, $personId);

        $this->expectExceptionMessageMatches('/already named as staff/');
        $this->staff->nameStaff($this->exhibitorId, $personId);
    }

    public function test_a_person_from_another_account_cannot_be_named(): void
    {
        $stranger = User::factory()->withAccount()->create();
        $foreignPerson = $this->makePerson((int) $stranger->accounts()->first()->id);

        $this->expectExceptionMessageMatches('/does not belong to this account/');
        $this->staff->nameStaff($this->exhibitorId, $foreignPerson);
    }

    public function test_withdrawing_staff_revokes_the_credential(): void
    {
        $this->makeExhibitorAccreditationType();
        $personId = $this->makePerson();
        $staffId = $this->staff->nameStaff($this->exhibitorId, $personId, actorUserId: $this->userId);

        $accreditationId = (int) DB::table('exhibitor_staff')->where('id', $staffId)->value('accreditation_id');
        $credentialId = $this->makeCredentialFor($accreditationId, $personId);

        $this->staff->withdrawStaff($staffId);

        $this->assertSame(
            'REVOKED',
            DB::table('credentials')->where('id', $credentialId)->value('status'),
            'Withdrawn staff must not keep a working badge.'
        );
        $this->assertSame(
            'WITHDRAWN',
            DB::table('accreditations')->where('id', $accreditationId)->value('status')
        );
    }

    public function test_withdrawing_staff_frees_a_quota_slot(): void
    {
        $first = $this->staff->nameStaff($this->exhibitorId, $this->makePerson());
        $this->staff->nameStaff($this->exhibitorId, $this->makePerson());

        $this->staff->withdrawStaff($first);

        $this->assertSame(1, $this->staff->namedStaffCount($this->exhibitorId));
        $this->staff->nameStaff($this->exhibitorId, $this->makePerson());
        $this->assertSame(2, $this->staff->namedStaffCount($this->exhibitorId));
    }

    // ---------------------------------------------------------------- booths

    public function test_a_booth_can_be_held_then_confirmed_then_built(): void
    {
        $boothId = $this->makeBooth('A12');

        $assignmentId = $this->booths->hold(
            eventId: $this->eventId,
            boothId: $boothId,
            eventExhibitorId: $this->exhibitorId,
            until: now()->addDays(7),
            actorUserId: $this->userId,
        );

        $this->booths->confirm($assignmentId, $this->userId);
        $this->assertSame('ASSIGNED', DB::table('booth_assignments')->where('id', $assignmentId)->value('status'));

        $this->booths->markBuilt($assignmentId);
        $this->assertSame('BUILT', DB::table('booth_assignments')->where('id', $assignmentId)->value('status'));
    }

    public function test_the_same_booth_cannot_be_assigned_twice(): void
    {
        $boothId = $this->makeBooth('A13');
        $other = $this->makeExhibitor(ExhibitorStatus::CONTRACTED, staffQuota: 1);

        $this->booths->assign($this->eventId, $boothId, $this->exhibitorId, $this->userId);

        $this->expectExceptionMessageMatches('/already held or assigned/');
        $this->booths->assign($this->eventId, $boothId, $other, $this->userId);
    }

    public function test_a_released_booth_can_be_assigned_again(): void
    {
        $boothId = $this->makeBooth('A14');

        $first = $this->booths->assign($this->eventId, $boothId, $this->exhibitorId, $this->userId);
        $this->booths->release($first, 'Exhibitor withdrew');

        $second = $this->booths->assign($this->eventId, $boothId, $this->exhibitorId, $this->userId);

        $this->assertNotSame($first, $second);
    }

    public function test_a_co_exhibitor_may_share_a_booth(): void
    {
        $boothId = $this->makeBooth('A15');
        $partner = $this->makeExhibitor(ExhibitorStatus::CONTRACTED, staffQuota: 1);

        $this->booths->assign($this->eventId, $boothId, $this->exhibitorId, $this->userId);
        $shared = $this->booths->assign($this->eventId, $boothId, $partner, $this->userId, 'CO_EXHIBITOR');

        $this->assertGreaterThan(0, $shared);
    }

    public function test_a_booth_from_another_event_is_refused(): void
    {
        $otherEventId = $this->makeEvent();
        $boothId = $this->makeBooth('B01', eventId: $otherEventId);

        $this->expectExceptionMessageMatches('/belongs to another event/');
        $this->booths->assign($this->eventId, $boothId, $this->exhibitorId, $this->userId);
    }

    public function test_a_booth_at_an_unused_venue_is_refused(): void
    {
        $otherVenueId = $this->makeVenue();
        $boothId = $this->makeBooth('C01', venueId: $otherVenueId);

        $this->expectExceptionMessageMatches('/venue this event does not use/');
        $this->booths->assign($this->eventId, $boothId, $this->exhibitorId, $this->userId);
    }

    public function test_expired_holds_are_released(): void
    {
        $boothId = $this->makeBooth('A16');

        $assignmentId = $this->booths->hold(
            eventId: $this->eventId,
            boothId: $boothId,
            eventExhibitorId: $this->exhibitorId,
            until: now()->addHour(),
        );

        DB::table('booth_assignments')->where('id', $assignmentId)->update(['held_until' => now()->subMinute()]);

        $this->assertSame(1, $this->booths->releaseExpiredHolds());
        $this->assertSame('RELEASED', DB::table('booth_assignments')->where('id', $assignmentId)->value('status'));
    }

    public function test_a_live_hold_is_not_released(): void
    {
        $boothId = $this->makeBooth('A17');

        $this->booths->hold(
            eventId: $this->eventId,
            boothId: $boothId,
            eventExhibitorId: $this->exhibitorId,
            until: now()->addDays(3),
        );

        $this->assertSame(0, $this->booths->releaseExpiredHolds());
    }

    public function test_only_a_held_booth_can_be_confirmed(): void
    {
        $boothId = $this->makeBooth('A18');
        $assignmentId = $this->booths->assign($this->eventId, $boothId, $this->exhibitorId, $this->userId);

        $this->expectExceptionMessageMatches('/Only a held booth/');
        $this->booths->confirm($assignmentId, $this->userId);
    }

    // ---------------------------------------------------------------- leads

    public function test_a_scan_without_consent_records_traffic_but_transfers_nothing(): void
    {
        [$identifier] = $this->makeAttendeeCredential();

        $result = $this->leads->capture($this->exhibitorId, $identifier);

        $this->assertSame(LeadCaptureResolution::NO_CONSENT, $result->resolution);
        $this->assertNull($result->leadId);
        $this->assertFalse($result->transferredData());
        $this->assertSame(
            1,
            DB::table('lead_captures')->where('event_exhibitor_id', $this->exhibitorId)->count(),
            'Booth traffic counts must still include a scan that transferred nothing.'
        );
        $this->assertSame(0, DB::table('leads')->where('event_exhibitor_id', $this->exhibitorId)->count());
    }

    public function test_a_scan_with_consent_creates_a_lead_with_snapshotted_fields(): void
    {
        [$identifier, $personId] = $this->makeAttendeeCredential();
        $this->grantConsent($personId);

        $result = $this->leads->capture($this->exhibitorId, $identifier);

        $this->assertSame(LeadCaptureResolution::RESOLVED, $result->resolution);
        $this->assertNotNull($result->leadId);

        $shared = json_decode(
            (string) DB::table('leads')->where('id', $result->leadId)->value('shared_fields'),
            true
        );

        $this->assertSame('Lead', $shared['first_name']);
        $this->assertArrayNotHasKey(
            'date_of_birth',
            $shared,
            'A booth has no need of a date of birth, and the consent text does not name it.'
        );
    }

    public function test_a_snapshot_does_not_change_when_the_person_is_edited(): void
    {
        [$identifier, $personId] = $this->makeAttendeeCredential();
        $this->grantConsent($personId);

        $result = $this->leads->capture($this->exhibitorId, $identifier);

        DB::table('persons')->where('id', $personId)->update(['first_name' => 'Renamed']);

        $shared = json_decode(
            (string) DB::table('leads')->where('id', $result->leadId)->value('shared_fields'),
            true
        );

        $this->assertSame(
            'Lead',
            $shared['first_name'],
            'What the exhibitor holds must be what was transferred, provable after the fact.'
        );
    }

    public function test_a_rescan_bumps_the_count_rather_than_creating_a_second_lead(): void
    {
        [$identifier, $personId] = $this->makeAttendeeCredential();
        $this->grantConsent($personId);

        $first = $this->leads->capture($this->exhibitorId, $identifier);
        $second = $this->leads->capture($this->exhibitorId, $identifier);

        $this->assertSame($first->leadId, $second->leadId);
        $this->assertSame(1, DB::table('leads')->where('event_exhibitor_id', $this->exhibitorId)->count());
        $this->assertSame(2, (int) DB::table('leads')->where('id', $first->leadId)->value('capture_count'));
        $this->assertSame(2, DB::table('lead_captures')->where('event_exhibitor_id', $this->exhibitorId)->count());
    }

    public function test_an_unknown_badge_is_recorded_as_unknown(): void
    {
        $result = $this->leads->capture($this->exhibitorId, 'not-a-real-identifier');

        $this->assertSame(LeadCaptureResolution::UNKNOWN_IDENTIFIER, $result->resolution);
        $this->assertSame(1, DB::table('lead_captures')->count());
    }

    public function test_a_badge_from_another_event_is_recorded_as_other_event(): void
    {
        $otherEventId = $this->makeEvent();
        [$identifier] = $this->makeAttendeeCredential($otherEventId);

        $result = $this->leads->capture($this->exhibitorId, $identifier);

        $this->assertSame(LeadCaptureResolution::OTHER_EVENT, $result->resolution);
    }

    public function test_the_raw_identifier_is_never_stored(): void
    {
        [$identifier] = $this->makeAttendeeCredential();

        $this->leads->capture($this->exhibitorId, $identifier);

        $stored = (string) DB::table('lead_captures')->value('identifier_hash');

        $this->assertNotSame($identifier, $stored);
        $this->assertSame(hash('sha256', $identifier), $stored);
        $this->assertSame(
            0,
            DB::table('lead_captures')->where('identifier_hash', $identifier)->count(),
            'A queue of raw badge codes on a booth phone would be a bag of admissions.'
        );
    }

    public function test_a_replayed_capture_is_idempotent(): void
    {
        [$identifier, $personId] = $this->makeAttendeeCredential();
        $this->grantConsent($personId);
        $clientId = (string) Str::uuid();

        $first = $this->leads->capture($this->exhibitorId, $identifier, clientGeneratedId: $clientId);
        $second = $this->leads->capture($this->exhibitorId, $identifier, clientGeneratedId: $clientId);

        $this->assertTrue($second->replayed);
        $this->assertSame($first->leadId, $second->leadId);
        $this->assertSame(1, DB::table('lead_captures')->count());
        $this->assertSame(1, (int) DB::table('leads')->where('id', $first->leadId)->value('capture_count'));
    }

    public function test_consent_for_another_exhibitor_does_not_apply(): void
    {
        [$identifier, $personId] = $this->makeAttendeeCredential();
        $otherExhibitor = $this->makeExhibitor(ExhibitorStatus::ACTIVE, staffQuota: 1);

        $this->grantConsent($personId, $otherExhibitor);

        $this->assertSame(
            LeadCaptureResolution::NO_CONSENT,
            $this->leads->capture($this->exhibitorId, $identifier)->resolution
        );
        $this->assertSame(
            LeadCaptureResolution::RESOLVED,
            $this->leads->capture($otherExhibitor, $identifier)->resolution
        );
    }

    public function test_withdrawn_consent_stops_transferring_data(): void
    {
        [$identifier, $personId] = $this->makeAttendeeCredential();
        $this->grantConsent($personId);

        DB::table('lead_consents')->where('person_id', $personId)->update(['withdrawn_at' => now()]);

        $this->assertSame(
            LeadCaptureResolution::NO_CONSENT,
            $this->leads->capture($this->exhibitorId, $identifier)->resolution
        );
    }

    public function test_capture_stats_break_down_by_resolution(): void
    {
        [$consented, $personId] = $this->makeAttendeeCredential();
        $this->grantConsent($personId);
        [$refused] = $this->makeAttendeeCredential();

        $this->leads->capture($this->exhibitorId, $consented);
        $this->leads->capture($this->exhibitorId, $refused);
        $this->leads->capture($this->exhibitorId, 'unknown-badge');

        $stats = $this->leads->captureStats($this->exhibitorId);

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['RESOLVED']);
        $this->assertSame(1, $stats['NO_CONSENT']);
        $this->assertSame(1, $stats['UNKNOWN_IDENTIFIER']);
    }

    public function test_an_unknown_exhibitor_is_refused(): void
    {
        $this->expectException(ResourceConflictException::class);
        $this->leads->capture(99999999, 'anything');
    }

    // ---------------------------------------------------------------- fixtures

    private function grantConsent(int $personId, ?int $eventExhibitorId = null): void
    {
        DB::table('lead_consents')->insert([
            'short_id' => 'lc_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'person_id' => $personId,
            'event_exhibitor_id' => $eventExhibitorId,
            'purpose' => 'EXHIBITOR_LEAD_SHARING',
            'shared_field_names' => json_encode(['first_name', 'last_name', 'company', 'job_title', 'email']),
            'consent_text' => 'Sharing my name, company, job title and email with exhibitors I visit.',
            'granted_at' => now(),
            'source' => 'CHECKOUT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function makeAttendeeCredential(?int $eventId = null): array
    {
        $eventId ??= $this->eventId;
        $personId = $this->makePerson();

        // Through the service, so the fixture cannot drift from the format the resolver
        // expects: a hand-rolled identifier that hashes differently reads as an unknown
        // credential and the test then proves nothing about consent.
        $identifierService = app(CredentialIdentifierService::class);
        $identifier = $identifierService->generate();

        DB::table('credentials')->insert([
            'short_id' => 'cr_'.Str::lower(Str::random(20)),
            'event_id' => $eventId,
            'person_id' => $personId,
            'accreditation_id' => $this->makeAccreditation($eventId, $personId),
            'credential_type' => 'ATTENDEE',
            'status' => 'ACTIVE',
            'identifier' => $identifier,
            'identifier_hash' => $identifierService->hash($identifier),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$identifier, $personId];
    }

    private function makeAccreditation(int $eventId, int $personId): int
    {
        $typeId = (int) DB::table('accreditation_types')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(20)),
            'event_id' => $eventId,
            'code' => 'VISITOR'.Str::upper(Str::random(4)),
            'name' => 'Visitor',
            'requires_approval' => false,
            'requires_photo' => false,
            'requires_id_document' => false,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('accreditations')->insertGetId([
            'short_id' => 'ac_'.Str::lower(Str::random(20)),
            'event_id' => $eventId,
            'person_id' => $personId,
            'accreditation_type_id' => $typeId,
            'status' => 'APPROVED',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeCredentialFor(int $accreditationId, int $personId): int
    {
        $identifierService = app(CredentialIdentifierService::class);
        $identifier = $identifierService->generate();

        return (int) DB::table('credentials')->insertGetId([
            'short_id' => 'cr_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'person_id' => $personId,
            'accreditation_id' => $accreditationId,
            'credential_type' => 'EXHIBITOR',
            'status' => 'ACTIVE',
            'identifier' => $identifier,
            'identifier_hash' => $identifierService->hash($identifier),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeExhibitorAccreditationType(): int
    {
        return (int) DB::table('accreditation_types')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'code' => 'EXHIBITOR',
            'name' => 'Exhibitor',
            'requires_approval' => false,
            'requires_photo' => false,
            'requires_id_document' => false,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeBooth(string $code, ?int $eventId = null, ?int $venueId = null): int
    {
        return (int) DB::table('booths')->insertGetId([
            'short_id' => 'bt_'.Str::lower(Str::random(20)),
            'venue_id' => $venueId ?? $this->venueId,
            'event_id' => $eventId,
            'code' => $code.Str::upper(Str::random(4)),
            'name' => 'Booth '.$code,
            'booth_type' => 'STANDARD',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeExhibitor(ExhibitorStatus $status, ?int $staffQuota): int
    {
        return (int) DB::table('event_exhibitors')->insertGetId([
            'short_id' => 'ee_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'company_id' => $this->makeCompany(),
            'status' => $status->value,
            'staff_pass_quota' => $staffQuota,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeCompany(): int
    {
        return (int) DB::table('companies')->insertGetId([
            'short_id' => 'co_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Company '.Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePerson(?int $accountId = null): int
    {
        return (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $accountId ?? $this->accountId,
            'first_name' => 'Lead',
            'last_name' => 'Person',
            'company' => 'Visitor Co',
            'job_title' => 'Buyer',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeVenue(): int
    {
        return (int) DB::table('venues')->insertGetId([
            'short_id' => 'vn_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Exhibition Venue',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Exhibitor Organizer',
            'email' => 'ex-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Exhibitor Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(10),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
