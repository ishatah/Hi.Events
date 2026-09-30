<?php

namespace Tests\Feature\Services\Domain\Device;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Credential\CredentialIdentifierService;
use HiEvents\Services\Domain\Device\AccessReconciliationService;
use HiEvents\Services\Domain\Device\DeviceEnrolmentService;
use HiEvents\Services\Domain\Device\DeviceSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceSyncServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DeviceSyncService $sync;

    private AccessReconciliationService $reconciliation;

    private DeviceEnrolmentService $enrolment;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $venueId;

    private int $zoneId;

    private int $accessPointId;

    private int $deviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sync = app(DeviceSyncService::class);
        $this->reconciliation = app(AccessReconciliationService::class);
        $this->enrolment = app(DeviceEnrolmentService::class);

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
        $this->zoneId = $this->makeZone();
        $this->accessPointId = $this->makeAccessPoint();
        $this->deviceId = $this->makeActiveDevice();
    }

    // ---------------------------------------------------------------- pull

    public function test_a_first_sync_returns_the_full_configuration(): void
    {
        $result = $this->sync->sync($this->deviceId, null);

        $this->assertSame($this->eventId, $result->config['event_id']);
        $this->assertCount(1, $result->config['zones']);
        $this->assertCount(1, $result->config['access_points']);
        $this->assertNotSame('', $result->newCursor);
    }

    public function test_a_credential_created_since_the_cursor_is_returned(): void
    {
        $first = $this->sync->sync($this->deviceId, null);

        $this->makeCredential();

        $second = $this->sync->sync($this->deviceId, $first->newCursor);

        $this->assertCount(1, $second->credentialsDelta);
    }

    public function test_an_unchanged_roster_returns_no_delta(): void
    {
        $this->makeCredential();

        $first = $this->sync->sync($this->deviceId, null);
        $second = $this->sync->sync($this->deviceId, $first->newCursor);

        $this->assertSame([], $second->credentialsDelta);
    }

    public function test_the_cursor_holds_when_nothing_changed(): void
    {
        $this->makeCredential();

        $first = $this->sync->sync($this->deviceId, null);
        $second = $this->sync->sync($this->deviceId, $first->newCursor);

        $this->assertSame(
            $first->newCursor,
            $second->newCursor,
            'Advancing to now() would skip a row written in the same second as the sync.'
        );
    }

    public function test_a_corrupted_cursor_causes_a_full_refresh_rather_than_an_error(): void
    {
        $this->makeCredential();

        $result = $this->sync->sync($this->deviceId, 'not-a-timestamp');

        $this->assertCount(
            1,
            $result->credentialsDelta,
            'A device with corrupted local state must be able to recover by syncing.'
        );
    }

    public function test_the_deny_list_carries_revoked_credentials(): void
    {
        $active = $this->makeCredential();
        $revoked = $this->makeCredential();
        DB::table('credentials')->where('id', $revoked)->update(['status' => 'REVOKED']);

        $result = $this->sync->sync($this->deviceId, null);

        $revokedHash = (string) DB::table('credentials')->where('id', $revoked)->value('identifier_hash');
        $activeHash = (string) DB::table('credentials')->where('id', $active)->value('identifier_hash');

        $this->assertContains($revokedHash, $result->denyList);
        $this->assertNotContains($activeHash, $result->denyList);
    }

    public function test_the_deny_list_is_sent_in_full_regardless_of_the_cursor(): void
    {
        $revoked = $this->makeCredential();
        DB::table('credentials')->where('id', $revoked)->update(['status' => 'REVOKED']);

        $first = $this->sync->sync($this->deviceId, null);
        $second = $this->sync->sync($this->deviceId, $first->newCursor);

        $this->assertCount(
            1,
            $second->denyList,
            'A device that missed a delta must not keep admitting a revoked badge.'
        );
    }

    public function test_grants_are_returned_with_their_credentials(): void
    {
        $credentialId = $this->makeCredential();
        $this->grantZone($credentialId);

        $result = $this->sync->sync($this->deviceId, null);

        $this->assertCount(1, $result->grantsDelta);
        $this->assertSame($credentialId, (int) $result->grantsDelta[0]['credential_id']);
    }

    public function test_another_events_credentials_are_not_returned(): void
    {
        $otherEventId = $this->makeEvent();
        $this->makeCredential($otherEventId);

        $result = $this->sync->sync($this->deviceId, null);

        $this->assertSame([], $result->credentialsDelta);
    }

    // ---------------------------------------------------------------- push

    public function test_a_queued_offline_scan_is_accepted(): void
    {
        $credentialId = $this->makeCredential();
        $this->grantZone($credentialId);
        $identifier = (string) DB::table('credentials')->where('id', $credentialId)->value('identifier');

        $result = $this->sync->sync($this->deviceId, null, [
            [
                'client_generated_id' => (string) Str::uuid(),
                'identifier' => $identifier,
                'access_point_id' => $this->accessPointId,
                'occurred_at' => now()->subMinutes(5)->toIso8601String(),
                'result' => 'GRANTED',
            ],
        ]);

        $this->assertSame(1, $result->acceptedLogs);
        $this->assertSame(1, DB::table('access_logs')->where('event_id', $this->eventId)->count());
    }

    public function test_replaying_the_same_scan_is_a_no_op(): void
    {
        $credentialId = $this->makeCredential();
        $this->grantZone($credentialId);
        $identifier = (string) DB::table('credentials')->where('id', $credentialId)->value('identifier');
        $clientId = (string) Str::uuid();

        $log = [
            'client_generated_id' => $clientId,
            'identifier' => $identifier,
            'access_point_id' => $this->accessPointId,
            'occurred_at' => now()->subMinutes(5)->toIso8601String(),
            'result' => 'GRANTED',
        ];

        $first = $this->sync->sync($this->deviceId, null, [$log]);
        $second = $this->sync->sync($this->deviceId, $first->newCursor, [$log]);

        $this->assertSame(1, $first->acceptedLogs);
        $this->assertSame(1, $second->duplicateLogs);
        $this->assertSame(
            1,
            DB::table('access_logs')->where('event_id', $this->eventId)->count(),
            'At-least-once delivery is only safe if a replay changes nothing.'
        );
    }

    public function test_a_scan_without_an_idempotency_key_is_rejected(): void
    {
        $result = $this->sync->sync($this->deviceId, null, [
            ['identifier' => 'anything', 'access_point_id' => $this->accessPointId],
        ]);

        $this->assertSame(0, $result->acceptedLogs);
        $this->assertCount(1, $result->rejectedLogs);
        $this->assertSame('missing_client_generated_id', $result->rejectedLogs[0]['reason']);
    }

    public function test_one_bad_row_does_not_cost_the_device_the_rest_of_its_queue(): void
    {
        $credentialId = $this->makeCredential();
        $this->grantZone($credentialId);
        $identifier = (string) DB::table('credentials')->where('id', $credentialId)->value('identifier');

        $result = $this->sync->sync($this->deviceId, null, [
            ['identifier' => 'no-key-here', 'access_point_id' => $this->accessPointId],
            [
                'client_generated_id' => (string) Str::uuid(),
                'identifier' => $identifier,
                'access_point_id' => $this->accessPointId,
                'result' => 'GRANTED',
            ],
        ]);

        $this->assertSame(1, $result->acceptedLogs);
        $this->assertCount(1, $result->rejectedLogs);
    }

    public function test_the_cursor_is_stored_on_the_device(): void
    {
        $this->makeCredential();

        $result = $this->sync->sync($this->deviceId, null);

        $this->assertSame(
            $result->newCursor,
            DB::table('devices')->where('id', $this->deviceId)->value('last_sync_cursor')
        );
    }

    public function test_a_suspended_device_cannot_sync(): void
    {
        $this->enrolment->suspend($this->deviceId, $this->accountId);

        $this->expectExceptionMessageMatches('/not active/');
        $this->sync->sync($this->deviceId, null);
    }

    public function test_a_device_with_no_event_cannot_sync(): void
    {
        DB::table('devices')->where('id', $this->deviceId)->update(['event_id' => null]);

        $this->expectExceptionMessageMatches('/not assigned to an event/');
        $this->sync->sync($this->deviceId, null);
    }

    public function test_an_unknown_device_is_refused(): void
    {
        $this->expectException(ResourceConflictException::class);
        $this->sync->sync(99999999, null);
    }

    // ---------------------------------------------------------------- reconciliation

    public function test_an_offline_grant_the_server_would_deny_is_flagged_not_rewritten(): void
    {
        $credentialId = $this->makeCredential();
        // Deliberately no grant, so the server denies what the device admitted offline.
        $identifier = (string) DB::table('credentials')->where('id', $credentialId)->value('identifier');

        $this->sync->sync($this->deviceId, null, [
            [
                'client_generated_id' => (string) Str::uuid(),
                'identifier' => $identifier,
                'access_point_id' => $this->accessPointId,
                'occurred_at' => now()->subMinutes(10)->toIso8601String(),
                'result' => 'GRANTED',
            ],
        ]);

        $findings = $this->reconciliation->openFindings($this->eventId);

        $this->assertCount(1, $findings);
        $this->assertSame('OFFLINE_GRANT_NOW_DENIED', $findings[0]->finding_type);
        $this->assertSame('HIGH', $findings[0]->severity);

        $log = DB::table('access_logs')->where('event_id', $this->eventId)->first();
        $this->assertStringStartsWith(
            'DENIED',
            (string) $log->result,
            'The log records the server decision; the disagreement is recorded beside it, not by editing it.'
        );
    }

    public function test_an_agreeing_scan_produces_no_finding(): void
    {
        $credentialId = $this->makeCredential();
        $this->grantZone($credentialId);
        $identifier = (string) DB::table('credentials')->where('id', $credentialId)->value('identifier');

        $this->sync->sync($this->deviceId, null, [
            [
                'client_generated_id' => (string) Str::uuid(),
                'identifier' => $identifier,
                'access_point_id' => $this->accessPointId,
                'result' => 'GRANTED',
            ],
        ]);

        $this->assertSame([], $this->reconciliation->openFindings($this->eventId));
    }

    public function test_the_same_problem_is_not_flagged_twice(): void
    {
        $credentialId = $this->makeCredential();
        $identifier = (string) DB::table('credentials')->where('id', $credentialId)->value('identifier');
        $clientId = (string) Str::uuid();

        $log = [
            'client_generated_id' => $clientId,
            'identifier' => $identifier,
            'access_point_id' => $this->accessPointId,
            'result' => 'GRANTED',
        ];

        $this->sync->sync($this->deviceId, null, [$log]);
        $this->sync->sync($this->deviceId, null, [$log]);

        $this->assertCount(
            1,
            $this->reconciliation->openFindings($this->eventId),
            'Re-running reconciliation must not pile up duplicates or the queue is unusable.'
        );
    }

    public function test_findings_are_ordered_with_the_most_severe_first(): void
    {
        $credentialId = $this->makeCredential();
        $identifier = (string) DB::table('credentials')->where('id', $credentialId)->value('identifier');

        $this->sync->sync($this->deviceId, null, [
            [
                'client_generated_id' => (string) Str::uuid(),
                'identifier' => $identifier,
                'access_point_id' => $this->accessPointId,
                'result' => 'GRANTED',
            ],
        ]);

        $logId = (int) DB::table('access_logs')->where('event_id', $this->eventId)->value('id');
        $this->reconciliation->flagPossibleDuplicatePerson($this->eventId, $logId, 1, 2);

        $findings = $this->reconciliation->openFindings($this->eventId);

        $this->assertSame('HIGH', $findings[0]->severity);
        $this->assertSame('LOW', $findings[1]->severity);
    }

    public function test_closing_a_finding_requires_a_note(): void
    {
        $credentialId = $this->makeCredential();
        $identifier = (string) DB::table('credentials')->where('id', $credentialId)->value('identifier');

        $this->sync->sync($this->deviceId, null, [
            [
                'client_generated_id' => (string) Str::uuid(),
                'identifier' => $identifier,
                'access_point_id' => $this->accessPointId,
                'result' => 'GRANTED',
            ],
        ]);

        $findingId = (int) $this->reconciliation->openFindings($this->eventId)[0]->id;

        $this->expectExceptionMessageMatches('/requires a note/');
        $this->reconciliation->review($findingId, $this->userId, '   ');
    }

    public function test_a_reviewed_finding_leaves_the_open_queue(): void
    {
        $credentialId = $this->makeCredential();
        $identifier = (string) DB::table('credentials')->where('id', $credentialId)->value('identifier');

        $this->sync->sync($this->deviceId, null, [
            [
                'client_generated_id' => (string) Str::uuid(),
                'identifier' => $identifier,
                'access_point_id' => $this->accessPointId,
                'result' => 'GRANTED',
            ],
        ]);

        $findingId = (int) $this->reconciliation->openFindings($this->eventId)[0]->id;

        $this->reconciliation->review($findingId, $this->userId, 'Checked with the gate supervisor');

        $this->assertSame([], $this->reconciliation->openFindings($this->eventId));
        $this->assertSame(1, $this->reconciliation->summary($this->eventId)['total']);
        $this->assertSame(0, $this->reconciliation->summary($this->eventId)['open']);
    }

    public function test_reviewing_a_closed_finding_is_refused(): void
    {
        $credentialId = $this->makeCredential();
        $identifier = (string) DB::table('credentials')->where('id', $credentialId)->value('identifier');

        $this->sync->sync($this->deviceId, null, [
            [
                'client_generated_id' => (string) Str::uuid(),
                'identifier' => $identifier,
                'access_point_id' => $this->accessPointId,
                'result' => 'GRANTED',
            ],
        ]);

        $findingId = (int) $this->reconciliation->openFindings($this->eventId)[0]->id;
        $this->reconciliation->review($findingId, $this->userId, 'Done');

        $this->expectExceptionMessageMatches('/not open for review/');
        $this->reconciliation->review($findingId, $this->userId, 'Again');
    }

    // ---------------------------------------------------------------- fixtures

    private function grantZone(int $credentialId): void
    {
        DB::table('access_grants')->insert([
            'short_id' => 'ag_'.Str::lower(Str::random(20)),
            'credential_id' => $credentialId,
            'zone_id' => $this->zoneId,
            'status' => 'ACTIVE',
            'allow_reentry' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeCredential(?int $eventId = null): int
    {
        $eventId ??= $this->eventId;

        $personId = (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Sync',
            'last_name' => 'Holder',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $typeId = (int) DB::table('accreditation_types')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(20)),
            'event_id' => $eventId,
            'code' => 'SY'.Str::upper(Str::random(6)),
            'name' => 'Sync Type',
            'requires_approval' => false,
            'requires_photo' => false,
            'requires_id_document' => false,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accreditationId = (int) DB::table('accreditations')->insertGetId([
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

        $identifier = app(CredentialIdentifierService::class)->generate();

        return (int) DB::table('credentials')->insertGetId([
            'short_id' => 'cr_'.Str::lower(Str::random(20)),
            'event_id' => $eventId,
            'person_id' => $personId,
            'accreditation_id' => $accreditationId,
            'credential_type' => 'STAFF',
            'status' => 'ACTIVE',
            'identifier' => $identifier,
            'identifier_hash' => app(CredentialIdentifierService::class)->hash($identifier),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeActiveDevice(): int
    {
        $registered = $this->enrolment->register(
            $this->accountId,
            'Sync Scanner',
            'SCANNER',
            $this->eventId,
            $this->accessPointId
        );
        $this->enrolment->pair($registered['pairing_code']);

        return $registered['device_id'];
    }

    private function makeAccessPoint(): int
    {
        return (int) DB::table('access_points')->insertGetId([
            'short_id' => 'ap_'.Str::lower(Str::random(20)),
            'zone_id' => $this->zoneId,
            'name' => 'Main Door',
            'code' => 'D'.Str::upper(Str::random(6)),
            'direction' => 'ENTRY',
            'access_point_type' => 'DOOR',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeZone(): int
    {
        return (int) DB::table('zones')->insertGetId([
            'short_id' => 'zn_'.Str::lower(Str::random(20)),
            'venue_id' => $this->venueId,
            'name' => 'Hall',
            'code' => 'H'.Str::upper(Str::random(6)),
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
            'name' => 'Sync Venue',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Sync Organizer',
            'email' => 'sy-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Sync Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(3),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
