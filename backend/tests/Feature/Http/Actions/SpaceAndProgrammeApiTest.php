<?php

namespace Tests\Feature\Http\Actions;

use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\AuthenticatesApiRequests;
use Tests\TestCase;

class SpaceAndProgrammeApiTest extends TestCase
{
    use AuthenticatesApiRequests;
    use DatabaseTransactions;

    private string $token;

    private string $foreignToken;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $foreignEventId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->token, $this->accountId, $this->userId] = $this->makeTenant();
        $this->eventId = $this->makeEvent($this->accountId, $this->userId);

        [$this->foreignToken, $foreignAccountId, $foreignUserId] = $this->makeTenant();
        $this->foreignEventId = $this->makeEvent($foreignAccountId, $foreignUserId);
    }

    public function test_a_venue_can_be_created_and_listed(): void
    {
        $response = $this->postJson('/venues', [
            'name' => 'Doha Exhibition Centre',
            'timezone' => 'Asia/Qatar',
            'default_capacity' => 5000,
        ], $this->authHeaders($this->token));

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Doha Exhibition Centre');
        $response->assertJsonPath('data.timezone', 'Asia/Qatar');

        $list = $this->getJson('/venues', $this->authHeaders($this->token));
        $list->assertOk();
        $this->assertCount(1, $list->json('data'));
    }

    public function test_venues_are_not_visible_to_another_account(): void
    {
        $this->postJson('/venues', ['name' => 'Private Venue'], $this->authHeaders($this->token))
            ->assertCreated();

        $list = $this->getJson('/venues', $this->authHeaders($this->foreignToken));

        $list->assertOk();
        $this->assertCount(0, $list->json('data'), 'A venue must not be visible to another account.');
    }

    public function test_a_zone_and_access_point_can_be_created(): void
    {
        $venueId = $this->createVenue();

        $zone = $this->postJson("/venues/{$venueId}/zones", [
            'name' => 'Backstage',
            'code' => 'BACKSTAGE',
            'zone_type' => 'BACKSTAGE',
            'capacity' => 50,
            'colour' => '#1B7E99',
        ], $this->authHeaders($this->token));

        $zone->assertCreated();
        $zone->assertJsonPath('data.code', 'BACKSTAGE');
        $zone->assertJsonPath('data.zone_type', 'BACKSTAGE');

        $zoneId = $zone->json('data.id');

        $accessPoint = $this->postJson("/zones/{$zoneId}/access-points", [
            'name' => 'Stage Door',
            'code' => 'STAGE_DOOR',
            'direction' => 'BIDIRECTIONAL',
        ], $this->authHeaders($this->token));

        $accessPoint->assertCreated();
        $accessPoint->assertJsonPath('data.direction', 'BIDIRECTIONAL');
    }

    public function test_an_access_point_direction_is_validated(): void
    {
        $venueId = $this->createVenue();
        $zoneId = $this->createZone($venueId);

        $this->postJson("/zones/{$zoneId}/access-points", [
            'name' => 'Bad Door',
            'code' => 'BAD',
            'direction' => 'SIDEWAYS',
        ], $this->authHeaders($this->token))->assertStatus(422);
    }

    public function test_a_session_can_be_created_updated_and_deleted(): void
    {
        $created = $this->postJson("/events/{$this->eventId}/sessions", [
            'title' => 'Opening Keynote',
            'starts_at' => '2030-06-01T09:00:00Z',
            'ends_at' => '2030-06-01T10:00:00Z',
            'session_type' => 'KEYNOTE',
        ], $this->authHeaders($this->token));

        $created->assertCreated();
        $created->assertJsonPath('data.title', 'Opening Keynote');

        $id = $created->json('data.id');

        $updated = $this->putJson("/events/{$this->eventId}/sessions/{$id}", [
            'title' => 'Opening Keynote (revised)',
            'starts_at' => '2030-06-01T09:30:00Z',
            'ends_at' => '2030-06-01T10:30:00Z',
        ], $this->authHeaders($this->token));

        $updated->assertOk();
        $updated->assertJsonPath('data.title', 'Opening Keynote (revised)');

        $this->deleteJson("/events/{$this->eventId}/sessions/{$id}", [], $this->authHeaders($this->token))
            ->assertSuccessful();

        $this->getJson("/events/{$this->eventId}/sessions/{$id}", $this->authHeaders($this->token))
            ->assertStatus(404);
    }

    public function test_a_session_ending_before_it_starts_is_rejected(): void
    {
        $this->postJson("/events/{$this->eventId}/sessions", [
            'title' => 'Impossible Session',
            'starts_at' => '2030-06-01T10:00:00Z',
            'ends_at' => '2030-06-01T09:00:00Z',
        ], $this->authHeaders($this->token))->assertStatus(422);
    }

    public function test_an_accreditation_type_can_be_created(): void
    {
        $response = $this->postJson("/events/{$this->eventId}/accreditation-types", [
            'code' => 'MEDIA',
            'name' => 'Media',
            'requires_approval' => true,
            'requires_photo' => true,
        ], $this->authHeaders($this->token));

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'MEDIA');
        $response->assertJsonPath('data.requires_photo', true);
    }

    public function test_an_access_rule_effect_is_validated(): void
    {
        $this->postJson("/events/{$this->eventId}/access-rules", [
            'name' => 'Nonsense rule',
            'effect' => 'MAYBE',
            'subject_type' => 'ALL',
            'target_type' => 'EVENT',
        ], $this->authHeaders($this->token))->assertStatus(422);
    }

    public function test_a_deny_access_rule_can_be_created(): void
    {
        $response = $this->postJson("/events/{$this->eventId}/access-rules", [
            'name' => 'Hall closed for cleaning',
            'effect' => 'DENY',
            'subject_type' => 'ALL',
            'target_type' => 'EVENT',
            'priority' => 1,
        ], $this->authHeaders($this->token));

        $response->assertCreated();
        $response->assertJsonPath('data.effect', 'DENY');
    }

    public function test_a_credential_requires_a_source(): void
    {
        // The exactly-one-source rule is enforced at the database too, but a bare request
        // should fail validation rather than reach a constraint violation.
        $this->postJson("/events/{$this->eventId}/credentials", [], $this->authHeaders($this->token))
            ->assertStatus(422);
    }

    public function test_a_credential_can_be_issued_for_an_attendee_and_revoked(): void
    {
        $attendeeId = $this->makeAttendee($this->eventId);

        $issued = $this->postJson("/events/{$this->eventId}/credentials", [
            'attendee_id' => $attendeeId,
        ], $this->authHeaders($this->token));

        $issued->assertCreated();
        $issued->assertJsonPath('data.status', 'ACTIVE');
        $issued->assertJsonPath('data.credential_type', 'ATTENDEE');

        $id = $issued->json('data.id');

        $this->postJson("/events/{$this->eventId}/credentials/{$id}/revoke", [
            'reason' => 'Badge reported lost',
        ], $this->authHeaders($this->token))->assertOk();

        $this->assertSame('REVOKED', DB::table('credentials')->where('id', $id)->value('status'));
    }

    public function test_revocation_requires_a_reason(): void
    {
        $attendeeId = $this->makeAttendee($this->eventId);

        $id = $this->postJson("/events/{$this->eventId}/credentials", [
            'attendee_id' => $attendeeId,
        ], $this->authHeaders($this->token))->assertCreated()->json('data.id');

        $this->postJson("/events/{$this->eventId}/credentials/{$id}/revoke", [], $this->authHeaders($this->token))
            ->assertStatus(422);
    }

    public function test_a_scan_returns_a_verdict_and_writes_a_log(): void
    {
        $venueId = $this->createVenue();
        $this->linkVenueToEvent($venueId, $this->eventId);
        $zoneId = $this->createZone($venueId);

        $accessPointId = $this->postJson("/zones/{$zoneId}/access-points", [
            'name' => 'Main Door',
            'code' => 'MAIN',
            'direction' => 'ENTRY',
        ], $this->authHeaders($this->token))->json('data.id');

        $response = $this->postJson("/events/{$this->eventId}/access-scans", [
            'identifier' => 'a-credential-that-does-not-exist',
            'access_point_id' => $accessPointId,
        ], $this->authHeaders($this->token));

        $response->assertOk();
        $response->assertJsonPath('granted', false);
        $response->assertJsonPath('result', 'DENIED_NO_CREDENTIAL');

        $logs = $this->getJson("/events/{$this->eventId}/access-logs", $this->authHeaders($this->token));
        $logs->assertOk();
        $this->assertNotEmpty($logs->json('data'));
    }

    public function test_a_simulated_scan_returns_a_verdict_without_writing_a_log(): void
    {
        $venueId = $this->createVenue();
        $this->linkVenueToEvent($venueId, $this->eventId);
        $zoneId = $this->createZone($venueId);

        $accessPointId = $this->postJson("/zones/{$zoneId}/access-points", [
            'name' => 'Sim Door',
            'code' => 'SIM',
            'direction' => 'ENTRY',
        ], $this->authHeaders($this->token))->json('data.id');

        $before = $this->getJson("/events/{$this->eventId}/access-logs", $this->authHeaders($this->token));
        $countBefore = count($before->json('data'));

        $response = $this->postJson("/events/{$this->eventId}/access-scans/simulate", [
            'identifier' => 'a-credential-that-does-not-exist',
            'access_point_id' => $accessPointId,
        ], $this->authHeaders($this->token));

        $response->assertOk();
        $response->assertJsonPath('granted', false);
        $response->assertJsonPath('result', 'DENIED_NO_CREDENTIAL');

        $after = $this->getJson("/events/{$this->eventId}/access-logs", $this->authHeaders($this->token));
        $this->assertCount($countBefore, $after->json('data'), 'A simulation must not write a log.');
    }

    public function test_a_simulated_scan_refuses_a_foreign_event(): void
    {
        $venueId = $this->createVenue();
        $zoneId = $this->createZone($venueId);

        $accessPointId = $this->postJson("/zones/{$zoneId}/access-points", [
            'name' => 'Sim Door',
            'code' => 'SIMX',
            'direction' => 'ENTRY',
        ], $this->authHeaders($this->token))->json('data.id');

        $response = $this->postJson("/events/{$this->foreignEventId}/access-scans/simulate", [
            'identifier' => 'anything',
            'access_point_id' => $accessPointId,
        ], $this->authHeaders($this->token));

        $this->assertContains($response->getStatusCode(), [401, 403, 404]);
    }

    /**
     * Every event-scoped endpoint must refuse an event belonging to another account. This
     * is the check that keeps 50 new routes from becoming 50 new leaks.
     */
    public function test_every_event_scoped_endpoint_refuses_a_foreign_event(): void
    {
        $paths = [
            'tracks', 'speakers', 'sessions',
            'accreditation-types', 'access-rules', 'access-logs', 'credentials',
        ];

        foreach ($paths as $path) {
            $response = $this->getJson(
                "/events/{$this->foreignEventId}/{$path}",
                $this->authHeaders($this->token)
            );

            $this->assertContains(
                $response->getStatusCode(),
                [401, 403, 404],
                sprintf('CROSS-TENANT LEAK: GET /events/{foreign}/%s returned %d.', $path, $response->getStatusCode())
            );
        }
    }

    private function createVenue(): int
    {
        return (int) $this->postJson('/venues', [
            'name' => 'Test Venue '.Str::random(6),
        ], $this->authHeaders($this->token))->json('data.id');
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

    private function createZone(int $venueId): int
    {
        return (int) $this->postJson("/venues/{$venueId}/zones", [
            'name' => 'Hall',
            'code' => 'HALL'.Str::upper(Str::random(4)),
        ], $this->authHeaders($this->token))->json('data.id');
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function makeTenant(): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = (int) $user->accounts()->first()->id;
        $token = JWTAuth::claims(['account_id' => $accountId])->fromUser($user);

        return [$token, $accountId, (int) $user->id];
    }

    private function makeEvent(int $accountId, int $userId): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'API Organizer',
            'email' => 'org-'.Str::lower(Str::random(10)).'@test.local',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'API Test Event',
            'account_id' => $accountId,
            'user_id' => $userId,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'ev_'.Str::lower(Str::random(16)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAttendee(int $eventId): int
    {
        $productId = (int) DB::table('products')->insertGetId([
            'title' => 'API Ticket',
            'event_id' => $eventId,
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
            'event_id' => $eventId,
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
            'event_id' => $eventId,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
}
