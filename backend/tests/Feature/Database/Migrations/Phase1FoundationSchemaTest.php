<?php

namespace Tests\Feature\Database\Migrations;

use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pins the invariants of the Phase 1 space, programme and identity schema.
 *
 * Several of these are enforced by database constraints rather than application code,
 * because the failure they prevent — a double-booked room, an un-recordable re-entry —
 * is only discovered on the event day.
 *
 * @see docs/arzo-master-plan/25-zones-and-permissions.md
 * @see docs/arzo-master-plan/27-sessions-tracks.md
 * @see docs/arzo-master-plan/24-access-control.md
 */
class Phase1FoundationSchemaTest extends TestCase
{
    use DatabaseTransactions;

    private ?int $accountId = null;

    private ?int $eventId = null;

    private ?int $userId = null;

    public function test_space_programme_and_identity_tables_exist(): void
    {
        foreach ([
            'venues', 'buildings', 'floors', 'zones', 'access_points', 'rooms',
            'seats', 'booths', 'event_venues',
            'tracks', 'speakers', 'sessions', 'session_speakers', 'session_products',
            'session_registrations', 'session_attendance',
            'access_logs', 'persons',
            'permissions', 'permission_roles', 'permission_role_permissions', 'event_users',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_attendees_carry_a_nullable_person_id(): void
    {
        $this->assertTrue(Schema::hasColumn('attendees', 'person_id'));
    }

    public function test_published_sessions_cannot_double_book_a_room(): void
    {
        $roomId = $this->makeRoom();
        $eventId = $this->makeEvent();

        $this->insertSession($eventId, $roomId, '2030-03-01 09:00:00+00', '2030-03-01 10:00:00+00', true);

        $this->expectExceptionMessageMatches('/sessions_no_room_overlap/');

        $this->insertSession($eventId, $roomId, '2030-03-01 09:30:00+00', '2030-03-01 10:30:00+00', true);
    }

    public function test_back_to_back_sessions_in_one_room_are_allowed(): void
    {
        $roomId = $this->makeRoom();
        $eventId = $this->makeEvent();

        $this->insertSession($eventId, $roomId, '2030-04-01 09:00:00+00', '2030-04-01 10:00:00+00', true);
        $second = $this->insertSession($eventId, $roomId, '2030-04-01 10:00:00+00', '2030-04-01 11:00:00+00', true);

        $this->assertNotNull($second, 'A session starting exactly when another ends must be allowed.');
    }

    public function test_unpublished_sessions_may_overlap(): void
    {
        $roomId = $this->makeRoom();
        $eventId = $this->makeEvent();

        $this->insertSession($eventId, $roomId, '2030-05-01 09:00:00+00', '2030-05-01 10:00:00+00', true);
        $draft = $this->insertSession($eventId, $roomId, '2030-05-01 09:30:00+00', '2030-05-01 10:30:00+00', false);

        $this->assertNotNull($draft, 'Drafts are work in progress and must be allowed to overlap.');
    }

    public function test_a_session_cannot_end_before_it_starts(): void
    {
        $eventId = $this->makeEvent();

        $this->expectExceptionMessageMatches('/sessions_ends_after_starts/');

        $this->insertSession($eventId, null, '2030-06-01 10:00:00+00', '2030-06-01 09:00:00+00', false);
    }

    public function test_zone_codes_are_unique_per_venue_but_reusable_across_venues(): void
    {
        $venueA = $this->makeVenue();
        $venueB = $this->makeVenue();

        $this->insertZone($venueA, 'BACKSTAGE');
        $this->insertZone($venueB, 'BACKSTAGE');

        $this->expectExceptionMessageMatches('/zones_venue_code_unique/');

        $this->insertZone($venueA, 'BACKSTAGE');
    }

    public function test_access_logs_accept_repeated_entries_for_one_attendee(): void
    {
        // attendee_check_ins carries a UNIQUE index on (attendee_id, check_in_list_id),
        // which makes re-entry impossible to record. access_logs must not repeat that.
        $eventId = $this->makeEvent();

        $first = $this->insertAccessLog($eventId, 'ENTRY');
        $exit = $this->insertAccessLog($eventId, 'EXIT');
        $second = $this->insertAccessLog($eventId, 'ENTRY');

        $this->assertNotNull($first);
        $this->assertNotNull($exit);
        $this->assertNotNull($second, 'Re-entry must be recordable; this is the whole point of access_logs.');
    }

    public function test_access_logs_reject_a_replayed_client_generated_id(): void
    {
        $eventId = $this->makeEvent();
        $clientId = (string) Str::uuid();

        $this->insertAccessLog($eventId, 'ENTRY', $clientId);

        $this->expectExceptionMessageMatches('/client_generated_id/');

        $this->insertAccessLog($eventId, 'ENTRY', $clientId);
    }

    public function test_permission_catalogue_is_seeded(): void
    {
        $count = DB::table('permissions')->count();

        $this->assertGreaterThanOrEqual(40, $count, 'The permission catalogue should be seeded.');

        foreach (['attendee.checkin', 'credential.issue', 'access.override', 'device.submit_scan'] as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name]);
        }
    }

    private function accountId(): int
    {
        if ($this->accountId === null) {
            // Reuse the factory the other feature tests use rather than hand-rolling an
            // accounts row; that table has required columns this test has no opinion about.
            $user = User::factory()->withAccount()->create();

            $this->userId = (int) $user->id;
            $this->accountId = (int) $user->accounts()->first()->id;
        }

        return $this->accountId;
    }

    private function makeVenue(): int
    {
        return DB::table('venues')->insertGetId([
            'short_id' => 'vn_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId(),
            'name' => 'Test Venue',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertZone(int $venueId, string $code): int
    {
        return DB::table('zones')->insertGetId([
            'short_id' => 'zn_'.Str::lower(Str::random(20)),
            'venue_id' => $venueId,
            'name' => $code,
            'code' => $code,
            'zone_type' => 'GENERAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeRoom(): int
    {
        return DB::table('rooms')->insertGetId([
            'short_id' => 'rm_'.Str::lower(Str::random(20)),
            'venue_id' => $this->makeVenue(),
            'name' => 'Hall A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        if ($this->eventId !== null) {
            return $this->eventId;
        }

        $accountId = $this->accountId();

        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Schema Organizer',
            'email' => 'org-'.Str::lower(Str::random(10)).'@test.local',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->eventId = (int) DB::table('events')->insertGetId([
            'title' => 'Schema Test Event',
            'account_id' => $accountId,
            'user_id' => $this->userId,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'ev_'.Str::lower(Str::random(16)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->eventId;
    }

    private function insertSession(
        int $eventId,
        ?int $roomId,
        string $startsAt,
        string $endsAt,
        bool $isPublished
    ): int {
        return DB::table('sessions')->insertGetId([
            'short_id' => 'ss_'.Str::lower(Str::random(20)),
            'event_id' => $eventId,
            'room_id' => $roomId,
            'title' => 'Test Session',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_published' => $isPublished,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertAccessLog(int $eventId, string $direction, ?string $clientId = null): int
    {
        return DB::table('access_logs')->insertGetId([
            'short_id' => 'al_'.Str::lower(Str::random(20)),
            'event_id' => $eventId,
            'occurred_at' => now(),
            'direction' => $direction,
            'result' => 'GRANTED',
            'client_generated_id' => $clientId,
            'created_at' => now(),
        ]);
    }
}
