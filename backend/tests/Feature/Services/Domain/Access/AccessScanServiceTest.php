<?php

namespace Tests\Feature\Services\Domain\Access;

use Carbon\Carbon;
use HiEvents\DomainObjects\CredentialDomainObject;
use HiEvents\DomainObjects\Enums\AccessDirection;
use HiEvents\DomainObjects\Enums\AccessLogSource;
use HiEvents\DomainObjects\Enums\AccessResult;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Access\AccessScanService;
use HiEvents\Services\Domain\Credential\CredentialIssuanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end: issue a credential, materialise its grants, scan it at a door, and check the
 * log. Exercises the real database, so it catches what the pure-function tests cannot —
 * grant materialisation, occupancy derivation and log idempotency.
 *
 * @see docs/arzo-master-plan/24-access-control.md
 */
class AccessScanServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AccessScanService $scanService;

    private CredentialIssuanceService $issuanceService;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $venueId;

    private int $zoneId;

    private int $accessPointId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scanService = app(AccessScanService::class);
        $this->issuanceService = app(CredentialIssuanceService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;

        $this->eventId = $this->makeEvent();
        $this->venueId = $this->makeVenue();
        $this->linkVenueToEvent($this->venueId, $this->eventId);
        $this->zoneId = $this->makeZone($this->venueId, 'HALL');
        $this->accessPointId = $this->makeAccessPoint($this->zoneId, 'DOOR1');
    }

    public function test_an_unknown_identifier_is_denied_and_logged(): void
    {
        $decision = $this->scanService->scan(
            eventId: $this->eventId,
            identifier: 'not-a-real-credential',
            accessPointId: $this->accessPointId,
        );

        $this->assertSame(AccessResult::DENIED_NO_CREDENTIAL, $decision->result);

        // A denial is the most operationally interesting event, so it must be recorded.
        $this->assertDatabaseHas('access_logs', [
            'event_id' => $this->eventId,
            'result' => AccessResult::DENIED_NO_CREDENTIAL->value,
            'raw_identifier' => 'not-a-real-credential',
        ]);
    }

    public function test_a_credential_without_grants_is_denied(): void
    {
        $credential = $this->issueAttendeeCredential();

        $decision = $this->scanService->scan(
            eventId: $this->eventId,
            identifier: $this->identifierFor($credential->getId()),
            accessPointId: $this->accessPointId,
        );

        $this->assertSame(AccessResult::DENIED_NO_GRANT, $decision->result);
    }

    public function test_a_credential_with_a_zone_grant_is_admitted(): void
    {
        $credential = $this->issueAttendeeCredential();
        $this->grantZone($credential->getId(), $this->zoneId);

        $decision = $this->scanService->scan(
            eventId: $this->eventId,
            identifier: $this->identifierFor($credential->getId()),
            accessPointId: $this->accessPointId,
        );

        $this->assertSame(AccessResult::GRANTED, $decision->result);
        $this->assertDatabaseHas('access_logs', [
            'credential_id' => $credential->getId(),
            'result' => AccessResult::GRANTED->value,
            'direction' => AccessDirection::ENTRY->value,
        ]);
    }

    public function test_re_entry_is_recorded_as_a_separate_log_row(): void
    {
        // The old attendee_check_ins table made this impossible: its unique index on
        // (attendee_id, check_in_list_id) forbade a second row.
        $credential = $this->issueAttendeeCredential();
        $this->grantZone($credential->getId(), $this->zoneId);
        $identifier = $this->identifierFor($credential->getId());

        $exitPointId = $this->makeAccessPoint($this->zoneId, 'EXIT1', 'EXIT');

        $this->scanService->scan($this->eventId, $identifier, $this->accessPointId);
        $this->scanService->scan($this->eventId, $identifier, $exitPointId);
        $this->scanService->scan($this->eventId, $identifier, $this->accessPointId);

        $this->assertSame(3, DB::table('access_logs')
            ->where('credential_id', $credential->getId())
            ->count());
    }

    public function test_a_revoked_credential_is_refused(): void
    {
        $credential = $this->issueAttendeeCredential();
        $this->grantZone($credential->getId(), $this->zoneId);
        $identifier = $this->identifierFor($credential->getId());

        $this->issuanceService->revoke($credential->getId(), $this->userId, 'Badge reported lost');

        $decision = $this->scanService->scan($this->eventId, $identifier, $this->accessPointId);

        $this->assertSame(AccessResult::DENIED_REVOKED, $decision->result);
    }

    public function test_a_replayed_offline_scan_is_idempotent(): void
    {
        $credential = $this->issueAttendeeCredential();
        $this->grantZone($credential->getId(), $this->zoneId);
        $identifier = $this->identifierFor($credential->getId());
        $clientId = (string) Str::uuid();

        $first = $this->scanService->scan(
            eventId: $this->eventId,
            identifier: $identifier,
            accessPointId: $this->accessPointId,
            clientGeneratedId: $clientId,
        );

        $second = $this->scanService->scan(
            eventId: $this->eventId,
            identifier: $identifier,
            accessPointId: $this->accessPointId,
            clientGeneratedId: $clientId,
        );

        $this->assertSame(AccessResult::GRANTED, $first->result);
        $this->assertSame(AccessResult::GRANTED, $second->result);

        $this->assertSame(1, DB::table('access_logs')
            ->where('client_generated_id', $clientId)
            ->count(), 'A replayed scan must not create a second log row.');
    }

    public function test_a_full_zone_refuses_entry(): void
    {
        DB::table('zones')->where('id', $this->zoneId)->update(['capacity' => 1]);

        $firstCredential = $this->issueAttendeeCredential();
        $this->grantZone($firstCredential->getId(), $this->zoneId);

        $secondCredential = $this->issueAttendeeCredential();
        $this->grantZone($secondCredential->getId(), $this->zoneId);

        $this->scanService->scan(
            $this->eventId,
            $this->identifierFor($firstCredential->getId()),
            $this->accessPointId
        );

        $decision = $this->scanService->scan(
            $this->eventId,
            $this->identifierFor($secondCredential->getId()),
            $this->accessPointId
        );

        $this->assertSame(AccessResult::DENIED_CAPACITY, $decision->result);
    }

    public function test_occupancy_falls_when_someone_exits(): void
    {
        DB::table('zones')->where('id', $this->zoneId)->update(['capacity' => 1]);
        $exitPointId = $this->makeAccessPoint($this->zoneId, 'EXIT1', 'EXIT');

        $first = $this->issueAttendeeCredential();
        $this->grantZone($first->getId(), $this->zoneId);
        $second = $this->issueAttendeeCredential();
        $this->grantZone($second->getId(), $this->zoneId);

        $this->scanService->scan($this->eventId, $this->identifierFor($first->getId()), $this->accessPointId);
        $this->scanService->scan($this->eventId, $this->identifierFor($first->getId()), $exitPointId);

        $decision = $this->scanService->scan(
            $this->eventId,
            $this->identifierFor($second->getId()),
            $this->accessPointId
        );

        $this->assertSame(AccessResult::GRANTED, $decision->result, 'An exit must free capacity.');
    }

    public function test_an_exit_point_records_an_exit_direction(): void
    {
        $credential = $this->issueAttendeeCredential();
        $this->grantZone($credential->getId(), $this->zoneId);
        $exitPointId = $this->makeAccessPoint($this->zoneId, 'EXIT1', 'EXIT');

        $this->scanService->scan($this->eventId, $this->identifierFor($credential->getId()), $exitPointId);

        $this->assertDatabaseHas('access_logs', [
            'credential_id' => $credential->getId(),
            'direction' => AccessDirection::EXIT->value,
        ]);
    }

    public function test_a_deny_rule_blocks_an_otherwise_valid_credential(): void
    {
        $credential = $this->issueAttendeeCredential();
        $this->grantZone($credential->getId(), $this->zoneId);

        DB::table('access_rules')->insert([
            'short_id' => 'ar_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'name' => 'Hall closed for cleaning',
            'priority' => 1,
            'effect' => 'DENY',
            'subject_type' => 'ALL',
            'target_type' => 'ZONE',
            'target_id' => $this->zoneId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $decision = $this->scanService->scan(
            $this->eventId,
            $this->identifierFor($credential->getId()),
            $this->accessPointId
        );

        $this->assertSame(AccessResult::DENIED_RULE, $decision->result);
    }

    public function test_the_device_clock_is_preserved_separately_from_the_server_clock(): void
    {
        $credential = $this->issueAttendeeCredential();
        $this->grantZone($credential->getId(), $this->zoneId);

        $scannedAt = Carbon::now()->subMinutes(30);

        $this->scanService->scan(
            eventId: $this->eventId,
            identifier: $this->identifierFor($credential->getId()),
            accessPointId: $this->accessPointId,
            clientGeneratedId: (string) Str::uuid(),
            occurredAt: $scannedAt,
            source: AccessLogSource::OFFLINE_SYNC->value,
        );

        $log = DB::table('access_logs')->where('credential_id', $credential->getId())->first();

        $this->assertNotNull($log);
        $this->assertTrue(
            Carbon::parse($log->recorded_at)->gt(Carbon::parse($log->occurred_at)),
            'recorded_at must be later than occurred_at for a late-submitted offline scan.'
        );
        $this->assertTrue((bool) $log->is_offline_replay);
    }

    public function test_a_slow_online_scan_is_not_flagged_as_an_offline_replay(): void
    {
        $credential = $this->issueAttendeeCredential();
        $this->grantZone($credential->getId(), $this->zoneId);

        // A retry on a bad network arrives late with a client id, which the old heuristic
        // read as an offline decision. Reconciliation would then look for a device decision
        // to compare against and find none.
        $this->scanService->scan(
            eventId: $this->eventId,
            identifier: $this->identifierFor($credential->getId()),
            accessPointId: $this->accessPointId,
            clientGeneratedId: (string) Str::uuid(),
            occurredAt: Carbon::now()->subMinutes(30),
        );

        $log = DB::table('access_logs')->where('credential_id', $credential->getId())->first();

        $this->assertFalse(
            (bool) $log->is_offline_replay,
            'The server decided this one, however long the record took to arrive.'
        );
    }

    public function test_a_fast_offline_replay_is_still_flagged(): void
    {
        $credential = $this->issueAttendeeCredential();
        $this->grantZone($credential->getId(), $this->zoneId);

        // A device that reconnects seconds after deciding offline. The old heuristic missed
        // this entirely, so a genuine offline admission went unreconciled.
        $this->scanService->scan(
            eventId: $this->eventId,
            identifier: $this->identifierFor($credential->getId()),
            accessPointId: $this->accessPointId,
            clientGeneratedId: (string) Str::uuid(),
            occurredAt: Carbon::now()->subSeconds(5),
            source: AccessLogSource::OFFLINE_SYNC->value,
        );

        $log = DB::table('access_logs')->where('credential_id', $credential->getId())->first();

        $this->assertTrue(
            (bool) $log->is_offline_replay,
            'The device made this decision, however quickly it reported it.'
        );
    }

    private function issueAttendeeCredential(): CredentialDomainObject
    {
        return $this->issuanceService->issueForAttendee(
            eventId: $this->eventId,
            attendeeId: $this->makeAttendee(),
        );
    }

    private function identifierFor(int $credentialId): string
    {
        return (string) DB::table('credentials')->where('id', $credentialId)->value('identifier');
    }

    private function grantZone(int $credentialId, int $zoneId): void
    {
        DB::table('access_grants')->insert([
            'short_id' => 'ag_'.Str::lower(Str::random(20)),
            'credential_id' => $credentialId,
            'zone_id' => $zoneId,
            'status' => 'ACTIVE',
            'allow_reentry' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Access Organizer',
            'email' => 'org-'.Str::lower(Str::random(10)).'@test.local',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Access Test Event',
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'ev_'.Str::lower(Str::random(16)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeVenue(): int
    {
        return (int) DB::table('venues')->insertGetId([
            'short_id' => 'vn_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Access Test Venue',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function linkVenueToEvent(int $venueId, int $eventId): void
    {
        DB::table('event_venues')->insert([
            'event_id' => $eventId,
            'venue_id' => $venueId,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeZone(int $venueId, string $code): int
    {
        return (int) DB::table('zones')->insertGetId([
            'short_id' => 'zn_'.Str::lower(Str::random(20)),
            'venue_id' => $venueId,
            'name' => $code,
            'code' => $code,
            'zone_type' => 'GENERAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAccessPoint(int $zoneId, string $code, string $direction = 'ENTRY'): int
    {
        return (int) DB::table('access_points')->insertGetId([
            'short_id' => 'ap_'.Str::lower(Str::random(20)),
            'zone_id' => $zoneId,
            'name' => $code,
            'code' => $code,
            'direction' => $direction,
            'access_point_type' => 'DOOR',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAttendee(?int &$productIdOut = null): int
    {
        $productId = (int) DB::table('products')->insertGetId([
            'title' => 'Access Ticket',
            'event_id' => $this->eventId,
            'type' => 'FREE',
            'product_type' => 'TICKET',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productIdOut = $productId;

        $productPriceId = (int) DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = (int) DB::table('orders')->insertGetId([
            'short_id' => 'or_'.Str::lower(Str::random(16)),
            'public_id' => 'O-'.Str::upper(Str::random(10)),
            'event_id' => $this->eventId,
            'status' => 'COMPLETED',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'first_name' => 'Buyer',
            'last_name' => 'One',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('attendees')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(16)),
            'public_id' => 'A-'.Str::upper(Str::random(10)),
            'first_name' => 'Test',
            'last_name' => 'Attendee',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $productPriceId,
            'event_id' => $this->eventId,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_an_event_allow_rule_for_another_product_is_not_granted_to_this_holder(): void
    {
        $productId = null;
        $attendeeId = $this->makeAttendee($productId);

        $restricted = $this->makeZone($this->venueId, 'VIP');

        DB::table('access_rules')->insert([
            'short_id' => 'ar_'.Str::lower(Str::random(16)),
            'event_id' => $this->eventId,
            'name' => 'VIP product only',
            'priority' => 10,
            'effect' => 'ALLOW',
            'subject_type' => 'PRODUCT',
            'subject_id' => $productId + 1000,
            'target_type' => 'ZONE',
            'target_id' => $restricted,
            'allow_reentry' => true,
            'enforce_capacity' => false,
            'requires_escort' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $credential = $this->issuanceService->issueForAttendee(
            eventId: $this->eventId,
            attendeeId: $attendeeId,
        );

        $grantedZones = DB::table('access_grants')
            ->where('credential_id', $credential->getId())
            ->pluck('zone_id')
            ->all();

        $this->assertNotContains(
            $restricted,
            array_map('intval', $grantedZones),
            'A rule naming another product must not be granted to this holder.'
        );
    }

    public function test_an_event_allow_rule_for_this_product_is_granted(): void
    {
        $productId = null;
        $attendeeId = $this->makeAttendee($productId);

        $hall = $this->makeZone($this->venueId, 'HALL2');

        DB::table('access_rules')->insert([
            'short_id' => 'ar_'.Str::lower(Str::random(16)),
            'event_id' => $this->eventId,
            'name' => 'This product',
            'priority' => 10,
            'effect' => 'ALLOW',
            'subject_type' => 'PRODUCT',
            'subject_id' => $productId,
            'target_type' => 'ZONE',
            'target_id' => $hall,
            'allow_reentry' => true,
            'enforce_capacity' => false,
            'requires_escort' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $credential = $this->issuanceService->issueForAttendee(
            eventId: $this->eventId,
            attendeeId: $attendeeId,
        );

        $grantedZones = array_map('intval', DB::table('access_grants')
            ->where('credential_id', $credential->getId())
            ->pluck('zone_id')
            ->all());

        $this->assertContains($hall, $grantedZones);
    }

    public function test_a_daily_window_is_evaluated_in_the_venue_timezone(): void
    {
        DB::table('venues')->where('id', $this->venueId)->update(['timezone' => 'Asia/Qatar']);

        $attendeeId = $this->makeAttendee();

        DB::table('access_rules')->insert([
            'short_id' => 'ar_'.Str::lower(Str::random(16)),
            'event_id' => $this->eventId,
            'name' => 'Business hours only',
            'priority' => 10,
            'effect' => 'ALLOW',
            'subject_type' => 'ALL',
            'subject_id' => null,
            'target_type' => 'ZONE',
            'target_id' => $this->zoneId,
            'time_from' => '09:00:00',
            'time_to' => '17:00:00',
            'allow_reentry' => true,
            'enforce_capacity' => false,
            'requires_escort' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $credential = $this->issuanceService->issueForAttendee(
            eventId: $this->eventId,
            attendeeId: $attendeeId,
        );

        $identifier = (string) DB::table('credentials')
            ->where('id', $credential->getId())
            ->value('identifier');

        // 07:30 UTC is 10:30 in Doha, inside the window. Evaluated as UTC it would fall
        // outside it, which is the defect this covers.
        Carbon::setTestNow(Carbon::parse('2030-06-01T07:30:00Z'));

        try {
            $decision = $this->scanService->scan(
                eventId: $this->eventId,
                identifier: $identifier,
                accessPointId: $this->accessPointId,
                direction: AccessDirection::ENTRY,
                operatorUserId: $this->userId,
                occurredAt: Carbon::parse('2030-06-01T07:30:00Z'),
            );
        } finally {
            Carbon::setTestNow();
        }

        $this->assertTrue(
            $decision->isGranted(),
            'A Doha venue window must be read in Asia/Qatar, not UTC. Got: '.$decision->reason
        );
    }

    public function test_simulation_agrees_with_the_real_scan_and_writes_no_log(): void
    {
        $attendeeId = $this->makeAttendee();

        $credential = $this->issuanceService->issueForAttendee(
            eventId: $this->eventId,
            attendeeId: $attendeeId,
        );

        $identifier = (string) DB::table('credentials')
            ->where('id', $credential->getId())
            ->value('identifier');

        $logsBefore = DB::table('access_logs')->where('event_id', $this->eventId)->count();

        $simulated = $this->scanService->simulate(
            eventId: $this->eventId,
            identifier: $identifier,
            accessPointId: $this->accessPointId,
            direction: AccessDirection::ENTRY,
            occurredAt: Carbon::now(),
        );

        $this->assertSame(
            $logsBefore,
            DB::table('access_logs')->where('event_id', $this->eventId)->count(),
            'A simulation must not record anything.'
        );

        $real = $this->scanService->scan(
            eventId: $this->eventId,
            identifier: $identifier,
            accessPointId: $this->accessPointId,
            direction: AccessDirection::ENTRY,
            operatorUserId: $this->userId,
            occurredAt: Carbon::now(),
        );

        $this->assertSame($real->result, $simulated->result);
        $this->assertSame($real->isGranted(), $simulated->isGranted());
    }

    public function test_simulation_reports_a_denial_without_recording_it(): void
    {
        $simulated = $this->scanService->simulate(
            eventId: $this->eventId,
            identifier: 'this-identifier-does-not-exist',
            accessPointId: $this->accessPointId,
            direction: AccessDirection::ENTRY,
        );

        $this->assertFalse($simulated->isGranted());
        $this->assertSame(AccessResult::DENIED_NO_CREDENTIAL, $simulated->result);
        $this->assertSame(0, DB::table('access_logs')->where('event_id', $this->eventId)->count());
    }

    public function test_an_access_point_from_another_event_is_refused(): void
    {
        $foreignVenue = $this->makeVenue();
        $foreignZone = $this->makeZone($foreignVenue, 'FOREIGN');
        $foreignPoint = $this->makeAccessPoint($foreignZone, 'FOREIGNDOOR');

        $decision = $this->scanService->scan(
            eventId: $this->eventId,
            identifier: 'anything',
            accessPointId: $foreignPoint,
            direction: AccessDirection::ENTRY,
            operatorUserId: $this->userId,
        );

        $this->assertFalse($decision->isGranted());
        $this->assertSame(AccessResult::DENIED_WRONG_POINT, $decision->result);
    }

    public function test_an_inactive_access_point_is_refused(): void
    {
        DB::table('access_points')->where('id', $this->accessPointId)->update(['is_active' => false]);

        $decision = $this->scanService->scan(
            eventId: $this->eventId,
            identifier: 'anything',
            accessPointId: $this->accessPointId,
            direction: AccessDirection::ENTRY,
            operatorUserId: $this->userId,
        );

        $this->assertSame(AccessResult::DENIED_WRONG_POINT, $decision->result);
    }

    public function test_a_replayed_client_id_from_another_event_does_not_return_this_events_verdict(): void
    {
        $clientId = (string) Str::uuid();

        $this->scanService->scan(
            eventId: $this->eventId,
            identifier: 'first-scan',
            accessPointId: $this->accessPointId,
            direction: AccessDirection::ENTRY,
            operatorUserId: $this->userId,
            clientGeneratedId: $clientId,
        );

        $otherEventId = $this->makeEvent();
        $this->linkVenueToEvent($this->venueId, $otherEventId);

        $decision = $this->scanService->scan(
            eventId: $otherEventId,
            identifier: 'second-scan',
            accessPointId: $this->accessPointId,
            direction: AccessDirection::ENTRY,
            operatorUserId: $this->userId,
            clientGeneratedId: $clientId,
        );

        $this->assertNotSame(
            'Already recorded; replay ignored.',
            $decision->reason,
            'A replay lookup must not cross events.'
        );
    }

    public function test_a_device_clock_far_in_the_future_is_not_trusted(): void
    {
        $decision = $this->scanService->scan(
            eventId: $this->eventId,
            identifier: 'anything',
            accessPointId: $this->accessPointId,
            direction: AccessDirection::ENTRY,
            operatorUserId: $this->userId,
            occurredAt: Carbon::now()->addYears(5),
        );

        $recorded = Carbon::parse((string) DB::table('access_logs')
            ->where('event_id', $this->eventId)
            ->orderByDesc('id')
            ->value('occurred_at'));

        $this->assertLessThan(
            60,
            abs($recorded->diffInSeconds(Carbon::now())),
            'An implausible device clock must fall back to the server clock.'
        );
        $this->assertFalse($decision->isGranted());
    }

    public function test_the_scanning_device_is_recorded(): void
    {
        $deviceId = (int) DB::table('devices')->insertGetId([
            'short_id' => 'dv_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'event_id' => $this->eventId,
            'name' => 'Gate Scanner 1',
            'device_type' => 'SCANNER',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->scanService->scan(
            eventId: $this->eventId,
            identifier: 'anything',
            accessPointId: $this->accessPointId,
            direction: AccessDirection::ENTRY,
            deviceId: $deviceId,
        );

        $this->assertSame(
            $deviceId,
            (int) DB::table('access_logs')
                ->where('event_id', $this->eventId)
                ->orderByDesc('id')
                ->value('device_id')
        );
    }
}
