<?php

namespace Tests\Feature\Services\Domain\Access;

use HiEvents\DomainObjects\Enums\AccessDirection;
use HiEvents\DomainObjects\Enums\AccessResult;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Access\ZoneOccupancyService;
use HiEvents\Services\Domain\Credential\CredentialIdentifierService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ZoneOccupancyServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ZoneOccupancyService $service;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $zoneId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ZoneOccupancyService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;

        $this->eventId = $this->makeEvent();
        $this->zoneId = $this->makeZone();
    }

    public function test_occupancy_counts_people_currently_inside(): void
    {
        $first = $this->makeCredential();
        $second = $this->makeCredential();

        $this->log($first, AccessDirection::ENTRY);
        $this->log($second, AccessDirection::ENTRY);

        $this->assertSame(2, $this->service->recompute($this->zoneId, $this->eventId));
    }

    public function test_an_exit_reduces_occupancy(): void
    {
        $credentialId = $this->makeCredential();

        $this->log($credentialId, AccessDirection::ENTRY);
        $this->assertSame(1, $this->service->recompute($this->zoneId, $this->eventId));

        $this->log($credentialId, AccessDirection::EXIT);
        $this->assertSame(0, $this->service->recompute($this->zoneId, $this->eventId));
    }

    public function test_re_entry_counts_a_person_once(): void
    {
        $credentialId = $this->makeCredential();

        $this->log($credentialId, AccessDirection::ENTRY);
        $this->log($credentialId, AccessDirection::EXIT);
        $this->log($credentialId, AccessDirection::ENTRY);

        $this->assertSame(
            1,
            $this->service->recompute($this->zoneId, $this->eventId),
            'Somebody who stepped out and came back is one person inside, not two.'
        );
    }

    public function test_denied_scans_do_not_count(): void
    {
        $credentialId = $this->makeCredential();

        $this->log($credentialId, AccessDirection::ENTRY, AccessResult::DENIED_NO_GRANT);

        $this->assertSame(0, $this->service->recompute($this->zoneId, $this->eventId));
    }

    public function test_another_zones_traffic_does_not_count(): void
    {
        $otherZoneId = $this->makeZone('OTHER');
        $credentialId = $this->makeCredential();

        $this->log($credentialId, AccessDirection::ENTRY, AccessResult::GRANTED, $otherZoneId);

        $this->assertSame(0, $this->service->recompute($this->zoneId, $this->eventId));
        $this->assertSame(1, $this->service->recompute($otherZoneId, $this->eventId));
    }

    public function test_a_fresh_snapshot_is_read_instead_of_recomputing(): void
    {
        $credentialId = $this->makeCredential();
        $this->log($credentialId, AccessDirection::ENTRY);

        $this->service->capture($this->zoneId, $this->eventId, 100);

        // Another entry after the snapshot. current() should still report the snapshot,
        // which is the staleness the door tolerates in exchange for not rescanning history.
        $this->log($this->makeCredential(), AccessDirection::ENTRY);

        $this->assertSame(1, $this->service->current($this->zoneId, $this->eventId));
        $this->assertSame(2, $this->service->recompute($this->zoneId, $this->eventId));
    }

    public function test_a_stale_snapshot_is_ignored(): void
    {
        $this->log($this->makeCredential(), AccessDirection::ENTRY);

        DB::table('zone_occupancy_snapshots')->insert([
            'zone_id' => $this->zoneId,
            'event_id' => $this->eventId,
            'occupancy' => 99,
            'capacity' => 100,
            'captured_at' => now()->subMinutes(5),
        ]);

        $this->assertSame(
            1,
            $this->service->current($this->zoneId, $this->eventId),
            'A snapshot older than the freshness window must not be trusted.'
        );
    }

    public function test_capacity_enforcement_is_detected_per_event(): void
    {
        $this->assertFalse($this->service->isEnforced($this->eventId));

        DB::table('access_rules')->insert([
            'short_id' => 'ar_'.Str::lower(Str::random(16)),
            'event_id' => $this->eventId,
            'name' => 'Capacity rule',
            'priority' => 10,
            'effect' => 'ALLOW',
            'subject_type' => 'ALL',
            'target_type' => 'ZONE',
            'target_id' => $this->zoneId,
            'allow_reentry' => true,
            'enforce_capacity' => true,
            'requires_escort' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(
            $this->service->isEnforced($this->eventId),
            'Occupancy is only worth computing when a rule actually consults it.'
        );
    }

    public function test_an_inactive_capacity_rule_does_not_trigger_enforcement(): void
    {
        DB::table('access_rules')->insert([
            'short_id' => 'ar_'.Str::lower(Str::random(16)),
            'event_id' => $this->eventId,
            'name' => 'Disabled capacity rule',
            'priority' => 10,
            'effect' => 'ALLOW',
            'subject_type' => 'ALL',
            'target_type' => 'ZONE',
            'target_id' => $this->zoneId,
            'allow_reentry' => true,
            'enforce_capacity' => true,
            'requires_escort' => false,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse($this->service->isEnforced($this->eventId));
    }

    private function log(
        int $credentialId,
        AccessDirection $direction,
        AccessResult $result = AccessResult::GRANTED,
        ?int $zoneId = null,
    ): void {
        DB::table('access_logs')->insert([
            'short_id' => 'al_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'credential_id' => $credentialId,
            'zone_id' => $zoneId ?? $this->zoneId,
            'occurred_at' => now(),
            'recorded_at' => now(),
            'direction' => $direction->value,
            'result' => $result->value,
            'identifier_type' => 'QR',
            'source' => 'SCAN',
            'is_offline_replay' => false,
            'created_at' => now(),
        ]);
    }

    /**
     * A credential needs exactly one source, which the credentials_exactly_one_source CHECK
     * enforces, so this goes through an accreditation rather than a bare person.
     */
    private function makeCredential(): int
    {
        $identifier = app(CredentialIdentifierService::class)->generate();
        $personId = $this->makePerson();

        return (int) DB::table('credentials')->insertGetId([
            'short_id' => 'cr_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'person_id' => $personId,
            'accreditation_id' => $this->makeAccreditation($personId),
            'credential_type' => 'STAFF',
            'status' => 'ACTIVE',
            'identifier' => $identifier,
            'identifier_hash' => app(CredentialIdentifierService::class)->hash($identifier),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAccreditation(int $personId): int
    {
        $typeId = (int) DB::table('accreditation_types')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'code' => 'STAFF'.Str::upper(Str::random(4)),
            'name' => 'Staff',
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
            'event_id' => $this->eventId,
            'person_id' => $personId,
            'accreditation_type_id' => $typeId,
            'status' => 'APPROVED',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePerson(): int
    {
        return (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Occ',
            'last_name' => 'Person',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeZone(string $code = 'HALL'): int
    {
        $venueId = (int) DB::table('venues')->insertGetId([
            'short_id' => 'vn_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Occupancy Venue',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('zones')->insertGetId([
            'short_id' => 'zn_'.Str::lower(Str::random(20)),
            'venue_id' => $venueId,
            'name' => $code,
            'code' => $code.Str::upper(Str::random(4)),
            'zone_type' => 'GENERAL',
            'capacity' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Occupancy Organizer',
            'email' => 'occ-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Occupancy Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(2),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_zone_with_its_own_capacity_enforces_without_a_rule(): void
    {
        $this->assertTrue(
            $this->service->isEnforced($this->eventId, $this->zoneId),
            'A zone marked as holding a fixed number must not quietly admit more.'
        );
    }

    public function test_a_zone_without_a_capacity_and_without_a_rule_does_not_enforce(): void
    {
        DB::table('zones')->where('id', $this->zoneId)->update(['capacity' => null]);

        $this->assertFalse($this->service->isEnforced($this->eventId, $this->zoneId));
    }
}
