<?php

namespace Tests\Feature\Http\Actions;

use HiEvents\Models\User;
use HiEvents\Services\Domain\Permission\RoleSeedService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\AuthenticatesApiRequests;
use Tests\TestCase;

class SessionRegistrationApiTest extends TestCase
{
    use AuthenticatesApiRequests;
    use DatabaseTransactions;

    private string $token;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $foreignEventId;

    private int $sessionId;

    protected function setUp(): void
    {
        parent::setUp();

        app(RoleSeedService::class)->seedSystemRoles();

        [$this->token, $this->accountId, $this->userId] = $this->makeTenant();
        $this->eventId = $this->makeEvent($this->accountId, $this->userId);

        [, $foreignAccountId, $foreignUserId] = $this->makeTenant();
        $this->foreignEventId = $this->makeEvent($foreignAccountId, $foreignUserId);

        $this->sessionId = $this->makeSession(['capacity' => 1, 'allow_waitlist' => true]);
    }

    public function test_registering_and_waitlisting_over_http(): void
    {
        $first = $this->makeAttendee();
        $second = $this->makeAttendee();

        $this->postJson(
            "/events/{$this->eventId}/sessions/{$this->sessionId}/registrations",
            ['attendee_id' => $first],
            $this->authHeaders($this->token)
        )->assertOk()->assertJsonPath('data.status', 'REGISTERED');

        $this->postJson(
            "/events/{$this->eventId}/sessions/{$this->sessionId}/registrations",
            ['attendee_id' => $second],
            $this->authHeaders($this->token)
        )->assertOk()
            ->assertJsonPath('waitlisted', true)
            ->assertJsonPath('waitlist_position', 1);

        $this->getJson(
            "/events/{$this->eventId}/sessions/{$this->sessionId}/stats",
            $this->authHeaders($this->token)
        )->assertOk()
            ->assertJsonPath('registered', 1)
            ->assertJsonPath('waitlisted', 1);
    }

    public function test_cancelling_promotes_over_http(): void
    {
        $first = $this->makeAttendee();
        $second = $this->makeAttendee();

        foreach ([$first, $second] as $attendeeId) {
            $this->postJson(
                "/events/{$this->eventId}/sessions/{$this->sessionId}/registrations",
                ['attendee_id' => $attendeeId],
                $this->authHeaders($this->token)
            )->assertOk();
        }

        $this->deleteJson(
            "/events/{$this->eventId}/sessions/{$this->sessionId}/registrations/{$first}",
            [],
            $this->authHeaders($this->token)
        )->assertOk()->assertJsonPath('promoted_attendee_id', $second);
    }

    public function test_registering_twice_returns_a_validation_error(): void
    {
        $attendeeId = $this->makeAttendee();

        $this->postJson(
            "/events/{$this->eventId}/sessions/{$this->sessionId}/registrations",
            ['attendee_id' => $attendeeId],
            $this->authHeaders($this->token)
        )->assertOk();

        $this->postJson(
            "/events/{$this->eventId}/sessions/{$this->sessionId}/registrations",
            ['attendee_id' => $attendeeId],
            $this->authHeaders($this->token)
        )->assertStatus(422);
    }

    public function test_the_session_ics_endpoint_returns_a_calendar(): void
    {
        $response = $this->get(
            "/events/{$this->eventId}/sessions/{$this->sessionId}/calendar.ics",
            $this->authHeaders($this->token)
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
        $this->assertStringContainsString('BEGIN:VEVENT', $response->getContent());
    }

    public function test_the_programme_ics_endpoint_returns_a_calendar(): void
    {
        $response = $this->get(
            "/events/{$this->eventId}/programme.ics",
            $this->authHeaders($this->token)
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
    }

    public function test_attendance_can_be_recorded_over_http(): void
    {
        $sessionId = $this->makeSession([
            'capacity' => 10,
            'check_in_enabled' => true,
            'requires_registration' => true,
        ]);
        $attendeeId = $this->makeAttendee();

        $this->postJson(
            "/events/{$this->eventId}/sessions/{$sessionId}/registrations",
            ['attendee_id' => $attendeeId],
            $this->authHeaders($this->token)
        )->assertOk();

        $this->postJson(
            "/events/{$this->eventId}/sessions/{$sessionId}/attendance",
            ['attendee_id' => $attendeeId],
            $this->authHeaders($this->token)
        )->assertOk()->assertJsonStructure(['id']);
    }

    public function test_the_session_endpoints_refuse_a_foreign_event(): void
    {
        $attendeeId = $this->makeAttendee();

        $paths = [
            ['POST', "/events/{$this->foreignEventId}/sessions/{$this->sessionId}/registrations"],
            ['GET', "/events/{$this->foreignEventId}/sessions/{$this->sessionId}/registrations"],
            ['GET', "/events/{$this->foreignEventId}/sessions/{$this->sessionId}/stats"],
            ['POST', "/events/{$this->foreignEventId}/sessions/{$this->sessionId}/attendance"],
            ['GET', "/events/{$this->foreignEventId}/programme.ics"],
        ];

        foreach ($paths as [$method, $path]) {
            $response = $this->json($method, $path, ['attendee_id' => $attendeeId], $this->authHeaders($this->token));

            $this->assertContains(
                $response->getStatusCode(),
                [401, 403, 404],
                sprintf('CROSS-TENANT LEAK: %s %s returned %d.', $method, $path, $response->getStatusCode())
            );
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSession(array $overrides = []): int
    {
        return (int) DB::table('sessions')->insertGetId(array_merge([
            'short_id' => 'ss_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'title' => 'API Session',
            'session_type' => 'TALK',
            'status' => 'SCHEDULED',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'timezone' => 'UTC',
            'requires_registration' => true,
            'allow_waitlist' => false,
            'check_in_enabled' => false,
            'is_published' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function makeAttendee(): int
    {
        $productId = (int) DB::table('products')->insertGetId([
            'title' => 'API Ticket',
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
            'first_name' => 'API',
            'last_name' => 'Buyer',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('attendees')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(16)),
            'public_id' => 'A-'.Str::upper(Str::random(10)),
            'first_name' => 'API',
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
            'name' => 'Session API Organizer',
            'email' => 'sapi-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Session API Event',
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'start_date' => now()->addDays(2),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
