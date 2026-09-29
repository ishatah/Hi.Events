<?php

namespace Tests\Feature\Services\Domain\Device;

use Carbon\Carbon;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Device\CredentialMediaService;
use HiEvents\Services\Domain\Device\DeviceEnrolmentService;
use HiEvents\Services\Domain\Device\DeviceFleetService;
use HiEvents\Services\Infrastructure\ApiKey\ApiKeyAuthenticator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceSubsystemTest extends TestCase
{
    use DatabaseTransactions;

    private DeviceEnrolmentService $enrolment;

    private DeviceFleetService $fleet;

    private CredentialMediaService $media;

    private ApiKeyAuthenticator $authenticator;

    private int $accountId;

    private int $userId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enrolment = app(DeviceEnrolmentService::class);
        $this->fleet = app(DeviceFleetService::class);
        $this->media = app(CredentialMediaService::class);
        $this->authenticator = app(ApiKeyAuthenticator::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent();
    }

    // ---------------------------------------------------------------- enrolment

    public function test_a_device_pairs_with_a_code_and_receives_a_key(): void
    {
        $registered = $this->enrolment->register($this->accountId, 'Gate 3 Scanner', 'SCANNER', $this->eventId);

        $this->assertSame(
            'PENDING',
            DB::table('devices')->where('id', $registered['device_id'])->value('status')
        );

        $paired = $this->enrolment->pair($registered['pairing_code']);

        $this->assertSame($registered['device_id'], $paired['device_id']);
        $this->assertStringStartsWith('arzod_', $paired['key']);
        $this->assertSame(
            'ACTIVE',
            DB::table('devices')->where('id', $registered['device_id'])->value('status')
        );
    }

    public function test_the_paired_key_authenticates(): void
    {
        $registered = $this->enrolment->register($this->accountId, 'Scanner', 'SCANNER', $this->eventId);
        $paired = $this->enrolment->pair($registered['pairing_code']);

        $principal = $this->authenticator->resolve($paired['key']);

        $this->assertNotNull($principal);
        $this->assertTrue($principal->isDevice());
        $this->assertSame($this->accountId, $principal->accountId);
    }

    public function test_a_pairing_code_cannot_be_used_twice(): void
    {
        $registered = $this->enrolment->register($this->accountId, 'Scanner', 'SCANNER', $this->eventId);

        $this->enrolment->pair($registered['pairing_code']);

        $this->expectExceptionMessageMatches('/not valid/');
        $this->enrolment->pair($registered['pairing_code']);
    }

    public function test_an_expired_pairing_code_is_refused(): void
    {
        $registered = $this->enrolment->register($this->accountId, 'Scanner', 'SCANNER', $this->eventId);

        DB::table('devices')
            ->where('id', $registered['device_id'])
            ->update(['pairing_code_expires_at' => now()->subMinute()]);

        $this->expectExceptionMessageMatches('/expired/');
        $this->enrolment->pair($registered['pairing_code']);
    }

    public function test_an_unknown_pairing_code_is_refused(): void
    {
        $this->expectException(ResourceConflictException::class);
        $this->enrolment->pair('NOTACODE');
    }

    public function test_a_pairing_code_avoids_ambiguous_characters(): void
    {
        for ($attempt = 0; $attempt < 15; $attempt++) {
            $registered = $this->enrolment->register($this->accountId, 'Scanner', 'SCANNER', $this->eventId);

            $this->assertSame(
                0,
                preg_match('/[O0I1L]/', $registered['pairing_code']),
                'A code typed on a tablet at a gate must not contain characters people confuse.'
            );
        }
    }

    public function test_suspending_a_device_revokes_its_key_immediately(): void
    {
        $registered = $this->enrolment->register($this->accountId, 'Lost Tablet', 'SCANNER', $this->eventId);
        $paired = $this->enrolment->pair($registered['pairing_code']);

        $this->assertNotNull($this->authenticator->resolve($paired['key']));

        $this->enrolment->suspend($registered['device_id'], $this->accountId);

        $this->assertNull(
            $this->authenticator->resolve($paired['key']),
            'Suspending is the revocation path for a lost tablet.'
        );
    }

    public function test_another_account_cannot_suspend_a_device(): void
    {
        $registered = $this->enrolment->register($this->accountId, 'Scanner', 'SCANNER', $this->eventId);
        $stranger = User::factory()->withAccount()->create();

        $this->expectExceptionMessageMatches('/could not be found/');
        $this->enrolment->suspend(
            $registered['device_id'],
            (int) $stranger->accounts()->first()->id
        );
    }

    public function test_rotating_a_key_invalidates_the_old_one(): void
    {
        $registered = $this->enrolment->register($this->accountId, 'Scanner', 'SCANNER', $this->eventId);
        $original = $this->enrolment->pair($registered['pairing_code'])['key'];

        $rotated = $this->enrolment->rotateKey($registered['device_id'], $this->accountId)['key'];

        $this->assertNull($this->authenticator->resolve($original));
        $this->assertNotNull($this->authenticator->resolve($rotated));
    }

    // ---------------------------------------------------------------- heartbeat and commands

    public function test_a_heartbeat_records_the_device_as_seen(): void
    {
        $deviceId = $this->makeActiveDevice();

        $this->fleet->heartbeat($deviceId, batteryLevel: 80, appVersion: '1.2.0');

        $device = DB::table('devices')->where('id', $deviceId)->first();

        $this->assertNotNull($device->last_seen_at);
        $this->assertSame(80, (int) $device->battery_level);
        $this->assertSame('1.2.0', $device->app_version);
    }

    public function test_clock_skew_is_reported(): void
    {
        $deviceId = $this->makeActiveDevice();

        $result = $this->fleet->heartbeat($deviceId, deviceClock: Carbon::now()->subMinutes(10));

        $this->assertTrue($result['skew_exceeded']);
        $this->assertGreaterThan(500, abs($result['clock_skew_seconds']));
        $this->assertSame(
            1,
            DB::table('device_health_events')->where('device_id', $deviceId)->where('state', 'CLOCK_SKEW')->count()
        );
    }

    public function test_a_small_clock_difference_is_tolerated(): void
    {
        $deviceId = $this->makeActiveDevice();

        $result = $this->fleet->heartbeat($deviceId, deviceClock: Carbon::now()->subSeconds(5));

        $this->assertFalse($result['skew_exceeded']);
        $this->assertSame(0, DB::table('device_health_events')->where('device_id', $deviceId)->count());
    }

    public function test_a_repeated_state_is_not_logged_twice(): void
    {
        $deviceId = $this->makeActiveDevice();

        $this->fleet->heartbeat($deviceId, batteryLevel: 10);
        $this->fleet->heartbeat($deviceId, batteryLevel: 9);
        $this->fleet->heartbeat($deviceId, batteryLevel: 8);

        $this->assertSame(
            1,
            DB::table('device_health_events')->where('device_id', $deviceId)->count(),
            'A row per heartbeat would be volume with no reader.'
        );
    }

    public function test_a_queued_command_is_delivered_on_the_next_heartbeat(): void
    {
        $deviceId = $this->makeActiveDevice();

        $commandId = $this->fleet->queueCommand($deviceId, 'FORCE_RESYNC', issuedByUserId: $this->userId);

        $result = $this->fleet->heartbeat($deviceId);

        $this->assertCount(1, $result['commands']);
        $this->assertSame(
            'DELIVERED',
            DB::table('device_commands')->where('id', $commandId)->value('status')
        );
    }

    public function test_a_delivered_command_is_not_delivered_again(): void
    {
        $deviceId = $this->makeActiveDevice();
        $this->fleet->queueCommand($deviceId, 'FORCE_RESYNC');

        $this->fleet->heartbeat($deviceId);
        $second = $this->fleet->heartbeat($deviceId);

        $this->assertSame([], $second['commands']);
    }

    public function test_a_device_acknowledges_a_command(): void
    {
        $deviceId = $this->makeActiveDevice();
        $commandId = $this->fleet->queueCommand($deviceId, 'WIPE');

        $this->fleet->heartbeat($deviceId);
        $this->fleet->acknowledgeCommand($commandId, $deviceId, 'Wiped');

        $command = DB::table('device_commands')->where('id', $commandId)->first();

        $this->assertSame('ACKNOWLEDGED', $command->status);
        $this->assertSame('Wiped', $command->result);
    }

    public function test_a_device_cannot_acknowledge_another_devices_command(): void
    {
        $mine = $this->makeActiveDevice();
        $theirs = $this->makeActiveDevice();
        $commandId = $this->fleet->queueCommand($mine, 'WIPE');

        $this->expectExceptionMessageMatches('/not outstanding for this device/');
        $this->fleet->acknowledgeCommand($commandId, $theirs);
    }

    public function test_the_fleet_status_separates_online_from_quiet_devices(): void
    {
        $online = $this->makeActiveDevice();
        $quiet = $this->makeActiveDevice();
        $this->enrolment->register($this->accountId, 'Unpaired', 'SCANNER', $this->eventId);

        $this->fleet->heartbeat($online);
        DB::table('devices')->where('id', $quiet)->update(['last_seen_at' => now()->subHour()]);

        $status = $this->fleet->fleetStatus($this->eventId);

        $this->assertSame(1, $status['online']);
        $this->assertSame(1, $status['offline']);
        $this->assertSame(1, $status['pending_pairing']);
        $this->assertSame($quiet, $status['offline_devices'][0]['device_id']);
    }

    public function test_a_device_that_never_reported_counts_as_offline(): void
    {
        $this->makeActiveDevice();

        $status = $this->fleet->fleetStatus($this->eventId);

        $this->assertSame(
            1,
            $status['offline'],
            'A device that loses power never gets to say it went offline.'
        );
    }

    // ---------------------------------------------------------------- credential media

    public function test_a_tag_is_encoded_against_a_credential(): void
    {
        $credentialId = $this->makeCredential();

        $mediaId = $this->media->encode($credentialId, 'RFID_WRISTBAND', 'TAG-UID-001', $this->userId);

        $row = DB::table('credential_media')->where('id', $mediaId)->first();

        $this->assertSame('ACTIVE', $row->status);
        $this->assertSame(hash('sha256', 'TAG-UID-001'), $row->uid_hash);
        $this->assertNotSame(
            'TAG-UID-001',
            $row->uid_hash,
            'A database copy must not hand somebody a list of tags to clone.'
        );
    }

    public function test_a_tag_cannot_be_live_on_two_credentials(): void
    {
        $first = $this->makeCredential();
        $second = $this->makeCredential();

        $this->media->encode($first, 'RFID_WRISTBAND', 'SHARED-TAG');

        $this->expectExceptionMessageMatches('/already active on another credential/');
        $this->media->encode($second, 'RFID_WRISTBAND', 'SHARED-TAG');
    }

    public function test_a_tag_resolves_to_its_credential(): void
    {
        $credentialId = $this->makeCredential();
        $this->media->encode($credentialId, 'NFC_CARD', 'RESOLVE-ME');

        $resolved = $this->media->resolveByUid('RESOLVE-ME');

        $this->assertNotNull($resolved);
        $this->assertSame($credentialId, (int) $resolved->id);
    }

    public function test_a_replaced_tag_no_longer_resolves(): void
    {
        $credentialId = $this->makeCredential();
        $mediaId = $this->media->encode($credentialId, 'RFID_WRISTBAND', 'OLD-TAG');

        $this->media->replace($mediaId, 'NEW-TAG', 'Lost on site', $this->userId);

        $this->assertNull(
            $this->media->resolveByUid('OLD-TAG'),
            'A replaced wristband presented at a door is not an admission.'
        );
        $this->assertNotNull($this->media->resolveByUid('NEW-TAG'));
    }

    public function test_a_replacement_links_to_what_it_replaces(): void
    {
        $credentialId = $this->makeCredential();
        $original = $this->media->encode($credentialId, 'RFID_WRISTBAND', 'FIRST');

        $replacement = $this->media->replace($original, 'SECOND', 'Damaged');

        $this->assertSame(
            $original,
            (int) DB::table('credential_media')->where('id', $replacement)->value('replaces_media_id'),
            'The chain is what makes a later clone detectable.'
        );
    }

    public function test_the_replaced_tag_uid_can_be_reused_after_deactivation(): void
    {
        $first = $this->makeCredential();
        $mediaId = $this->media->encode($first, 'RFID_WRISTBAND', 'RECYCLED');
        $this->media->deactivate($mediaId, 'Returned to stock');

        $second = $this->makeCredential();

        $this->assertGreaterThan(
            0,
            $this->media->encode($second, 'RFID_WRISTBAND', 'RECYCLED'),
            'Wristbands are physically recycled between events.'
        );
    }

    public function test_replacing_requires_a_reason(): void
    {
        $credentialId = $this->makeCredential();
        $mediaId = $this->media->encode($credentialId, 'RFID_WRISTBAND', 'TAG');

        $this->expectExceptionMessageMatches('/requires a reason/');
        $this->media->replace($mediaId, 'NEW', '   ');
    }

    public function test_media_cannot_be_encoded_for_a_revoked_credential(): void
    {
        $credentialId = $this->makeCredential();
        DB::table('credentials')->where('id', $credentialId)->update(['status' => 'REVOKED']);

        $this->expectExceptionMessageMatches('/only be encoded for an active credential/');
        $this->media->encode($credentialId, 'RFID_WRISTBAND', 'TAG');
    }

    public function test_media_history_shows_every_tag_a_credential_has_carried(): void
    {
        $credentialId = $this->makeCredential();
        $first = $this->media->encode($credentialId, 'RFID_WRISTBAND', 'ONE');
        $this->media->replace($first, 'TWO', 'Lost');

        $history = $this->media->historyFor($credentialId);

        $this->assertCount(2, $history);
        $this->assertSame('DEACTIVATED', $history[0]->status);
        $this->assertSame('ACTIVE', $history[1]->status);
    }

    public function test_deactivating_twice_is_refused(): void
    {
        $credentialId = $this->makeCredential();
        $mediaId = $this->media->encode($credentialId, 'RFID_WRISTBAND', 'TAG');

        $this->media->deactivate($mediaId, 'Lost');

        $this->expectExceptionMessageMatches('/already inactive/');
        $this->media->deactivate($mediaId, 'Lost again');
    }

    // ---------------------------------------------------------------- fixtures

    private function makeActiveDevice(): int
    {
        $registered = $this->enrolment->register($this->accountId, 'Device '.Str::random(4), 'SCANNER', $this->eventId);
        $this->enrolment->pair($registered['pairing_code']);

        // Cleared so a test can assert on the first heartbeat rather than the pairing one.
        DB::table('devices')->where('id', $registered['device_id'])->update(['last_seen_at' => null]);

        return $registered['device_id'];
    }

    private function makeCredential(): int
    {
        $personId = (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Media',
            'last_name' => 'Holder',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $typeId = (int) DB::table('accreditation_types')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'code' => 'ST'.Str::upper(Str::random(6)),
            'name' => 'Staff',
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
            'event_id' => $this->eventId,
            'person_id' => $personId,
            'accreditation_type_id' => $typeId,
            'status' => 'APPROVED',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $identifier = Str::lower(Str::random(40));

        return (int) DB::table('credentials')->insertGetId([
            'short_id' => 'cr_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'person_id' => $personId,
            'accreditation_id' => $accreditationId,
            'credential_type' => 'STAFF',
            'status' => 'ACTIVE',
            'identifier' => $identifier,
            'identifier_hash' => hash('sha256', $identifier),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Device Organizer',
            'email' => 'dv-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Device Event',
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
