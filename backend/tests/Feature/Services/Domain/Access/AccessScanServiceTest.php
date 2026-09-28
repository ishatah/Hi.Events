<?php

namespace Tests\Feature\Services\Domain\Access;

use Carbon\Carbon;
use HiEvents\DomainObjects\CredentialDomainObject;
use HiEvents\DomainObjects\Enums\AccessDirection;
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
        );

        $log = DB::table('access_logs')->where('credential_id', $credential->getId())->first();

        $this->assertNotNull($log);
        $this->assertTrue(
            Carbon::parse($log->recorded_at)->gt(Carbon::parse($log->occurred_at)),
            'recorded_at must be later than occurred_at for a late-submitted offline scan.'
        );
        $this->assertTrue((bool) $log->is_offline_replay);
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

    private function makeAttendee(): int
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
}
