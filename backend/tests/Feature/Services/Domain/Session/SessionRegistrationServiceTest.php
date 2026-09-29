<?php

namespace Tests\Feature\Services\Domain\Session;

use HiEvents\DomainObjects\Enums\AccessDirection;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Session\SessionAttendanceService;
use HiEvents\Services\Domain\Session\SessionConflictService;
use HiEvents\Services\Domain\Session\SessionIcsExportService;
use HiEvents\Services\Domain\Session\SessionRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionRegistrationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private SessionRegistrationService $registration;

    private SessionAttendanceService $attendance;

    private SessionIcsExportService $ics;

    private SessionConflictService $conflicts;

    private int $accountId;

    private int $userId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registration = app(SessionRegistrationService::class);
        $this->attendance = app(SessionAttendanceService::class);
        $this->ics = app(SessionIcsExportService::class);
        $this->conflicts = app(SessionConflictService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent();
    }

    public function test_an_attendee_can_register_for_a_session(): void
    {
        $sessionId = $this->makeSession(['capacity' => 10]);
        $attendeeId = $this->makeAttendee();

        $result = $this->registration->register($sessionId, $attendeeId);

        $this->assertFalse($result->waitlisted);
        $this->assertNotNull($result->registration);
        $this->assertSame(1, $this->registration->registeredCount($sessionId));
    }

    public function test_registering_twice_is_refused(): void
    {
        $sessionId = $this->makeSession(['capacity' => 10]);
        $attendeeId = $this->makeAttendee();

        $this->registration->register($sessionId, $attendeeId);

        $this->expectException(ResourceConflictException::class);
        $this->registration->register($sessionId, $attendeeId);
    }

    public function test_a_full_session_places_the_attendee_on_the_waitlist(): void
    {
        $sessionId = $this->makeSession(['capacity' => 1, 'allow_waitlist' => true]);

        $this->registration->register($sessionId, $this->makeAttendee());
        $result = $this->registration->register($sessionId, $this->makeAttendee());

        $this->assertTrue($result->waitlisted);
        $this->assertSame(1, $result->waitlistPosition);
        $this->assertSame(1, $this->registration->registeredCount($sessionId));
        $this->assertSame(1, $this->registration->waitlistCount($sessionId));
    }

    public function test_a_full_session_without_a_waitlist_refuses(): void
    {
        $sessionId = $this->makeSession(['capacity' => 1, 'allow_waitlist' => false]);

        $this->registration->register($sessionId, $this->makeAttendee());

        $this->expectExceptionMessage('This session is full.');
        $this->registration->register($sessionId, $this->makeAttendee());
    }

    public function test_capacity_is_never_exceeded(): void
    {
        $sessionId = $this->makeSession(['capacity' => 3, 'allow_waitlist' => true]);

        for ($i = 0; $i < 10; $i++) {
            $this->registration->register($sessionId, $this->makeAttendee());
        }

        $this->assertSame(3, $this->registration->registeredCount($sessionId));
        $this->assertSame(7, $this->registration->waitlistCount($sessionId));
    }

    public function test_cancelling_promotes_the_first_person_waiting(): void
    {
        $sessionId = $this->makeSession(['capacity' => 1, 'allow_waitlist' => true]);

        $first = $this->makeAttendee();
        $second = $this->makeAttendee();
        $third = $this->makeAttendee();

        $this->registration->register($sessionId, $first);
        $this->registration->register($sessionId, $second);
        $this->registration->register($sessionId, $third);

        $promoted = $this->registration->cancel($sessionId, $first);

        $this->assertSame($second, $promoted, 'The waitlist must be served in order.');
        $this->assertSame(1, $this->registration->registeredCount($sessionId));
        $this->assertSame(1, $this->registration->waitlistCount($sessionId));
    }

    public function test_cancelling_a_waitlist_place_promotes_nobody(): void
    {
        $sessionId = $this->makeSession(['capacity' => 1, 'allow_waitlist' => true]);

        $first = $this->makeAttendee();
        $second = $this->makeAttendee();

        $this->registration->register($sessionId, $first);
        $this->registration->register($sessionId, $second);

        $this->assertNull($this->registration->cancel($sessionId, $second));
        $this->assertSame(1, $this->registration->registeredCount($sessionId));
        $this->assertSame(0, $this->registration->waitlistCount($sessionId));
    }

    public function test_an_attendee_can_register_again_after_cancelling(): void
    {
        $sessionId = $this->makeSession(['capacity' => 5]);
        $attendeeId = $this->makeAttendee();

        $this->registration->register($sessionId, $attendeeId);
        $this->registration->cancel($sessionId, $attendeeId);
        $result = $this->registration->register($sessionId, $attendeeId);

        $this->assertFalse($result->waitlisted);
        $this->assertSame(1, $this->registration->registeredCount($sessionId));
    }

    public function test_an_unlimited_session_never_fills(): void
    {
        $sessionId = $this->makeSession(['capacity' => null]);

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($this->registration->register($sessionId, $this->makeAttendee())->waitlisted);
        }

        $this->assertSame(5, $this->registration->registeredCount($sessionId));
    }

    public function test_registration_before_the_window_opens_is_refused(): void
    {
        $sessionId = $this->makeSession([
            'capacity' => 10,
            'registration_opens_at' => now()->addDay(),
        ]);

        $this->expectExceptionMessage('Registration for this session has not opened yet.');
        $this->registration->register($sessionId, $this->makeAttendee());
    }

    public function test_registration_after_the_window_closes_is_refused(): void
    {
        $sessionId = $this->makeSession([
            'capacity' => 10,
            'registration_closes_at' => now()->subMinute(),
        ]);

        $this->expectExceptionMessage('Registration for this session has closed.');
        $this->registration->register($sessionId, $this->makeAttendee());
    }

    public function test_an_attendee_from_another_event_is_refused(): void
    {
        $sessionId = $this->makeSession(['capacity' => 10]);
        $otherEventId = $this->makeEvent();

        $this->expectExceptionMessage('That attendee does not belong to this event.');
        $this->registration->register($sessionId, $this->makeAttendee($otherEventId));
    }

    public function test_a_session_that_does_not_require_registration_is_refused(): void
    {
        $sessionId = $this->makeSession(['requires_registration' => false]);

        $this->expectExceptionMessage('This session does not require registration.');
        $this->registration->register($sessionId, $this->makeAttendee());
    }

    public function test_attendance_is_recorded_and_counted(): void
    {
        $sessionId = $this->makeSession(['capacity' => 10, 'check_in_enabled' => true]);
        $attendeeId = $this->makeAttendee();

        $this->registration->register($sessionId, $attendeeId);
        $this->attendance->record($sessionId, $attendeeId);

        $this->assertSame(1, $this->attendance->attendedCount($sessionId));
        $this->assertSame(0, $this->attendance->noShowCount($sessionId));
    }

    public function test_re_entry_does_not_inflate_the_attended_count(): void
    {
        $sessionId = $this->makeSession(['capacity' => 10, 'check_in_enabled' => true]);
        $attendeeId = $this->makeAttendee();

        $this->registration->register($sessionId, $attendeeId);
        $this->attendance->record($sessionId, $attendeeId, AccessDirection::ENTRY);
        $this->attendance->record($sessionId, $attendeeId, AccessDirection::EXIT);
        $this->attendance->record($sessionId, $attendeeId, AccessDirection::ENTRY);

        $this->assertSame(
            1,
            $this->attendance->attendedCount($sessionId),
            'Attendance counts distinct people, not scans.'
        );
        $this->assertSame(3, DB::table('session_attendance')->where('session_id', $sessionId)->count());
    }

    public function test_a_registered_attendee_who_never_scans_is_a_no_show(): void
    {
        $sessionId = $this->makeSession(['capacity' => 10, 'check_in_enabled' => true]);

        $attended = $this->makeAttendee();
        $absent = $this->makeAttendee();

        $this->registration->register($sessionId, $attended);
        $this->registration->register($sessionId, $absent);
        $this->attendance->record($sessionId, $attended);

        $this->assertSame(1, $this->attendance->noShowCount($sessionId));
    }

    public function test_a_replayed_attendance_scan_is_idempotent(): void
    {
        $sessionId = $this->makeSession(['capacity' => 10, 'check_in_enabled' => true]);
        $attendeeId = $this->makeAttendee();
        $clientId = (string) Str::uuid();

        $this->registration->register($sessionId, $attendeeId);

        $first = $this->attendance->record($sessionId, $attendeeId, clientGeneratedId: $clientId);
        $second = $this->attendance->record($sessionId, $attendeeId, clientGeneratedId: $clientId);

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::table('session_attendance')->where('session_id', $sessionId)->count());
    }

    public function test_attendance_requires_registration_when_the_session_does(): void
    {
        $sessionId = $this->makeSession([
            'capacity' => 10,
            'check_in_enabled' => true,
            'requires_registration' => true,
        ]);

        $this->expectExceptionMessage('That attendee is not registered for this session.');
        $this->attendance->record($sessionId, $this->makeAttendee());
    }

    public function test_attendance_is_refused_when_check_in_is_disabled(): void
    {
        $sessionId = $this->makeSession(['capacity' => 10, 'check_in_enabled' => false]);

        $this->expectExceptionMessage('Check-in is not enabled for this session.');
        $this->attendance->record($sessionId, $this->makeAttendee());
    }

    public function test_the_ics_export_is_well_formed(): void
    {
        $sessionId = $this->makeSession([
            'capacity' => 10,
            'title' => 'Opening; Keynote, with commas',
            'is_published' => true,
        ]);

        $calendar = $this->ics->forSession($sessionId);

        $this->assertNotNull($calendar);
        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $calendar);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $calendar);
        $this->assertStringContainsString('PRODID:-//ARZO//NONSGML Session Calendar//EN', $calendar);
        $this->assertStringContainsString('BEGIN:VEVENT', $calendar);
        $this->assertStringContainsString('@arzo.qa', $calendar);
        $this->assertStringContainsString(
            'Opening\\; Keynote\\, with commas',
            $calendar,
            'Semicolons and commas must be escaped per RFC 5545.'
        );
        $this->assertMatchesRegularExpression('/DTSTART:\d{8}T\d{6}Z/', $calendar);
    }

    public function test_the_ics_export_omits_unpublished_sessions(): void
    {
        $this->makeSession(['capacity' => 10, 'is_published' => false, 'title' => 'Draft Session']);

        $calendar = $this->ics->forEventProgramme($this->eventId);

        $this->assertStringNotContainsString('Draft Session', $calendar);
    }

    public function test_an_agenda_reports_overlapping_sessions(): void
    {
        $attendeeId = $this->makeAttendee();

        $morning = $this->makeSession([
            'capacity' => 10,
            'title' => 'Morning',
            'starts_at' => now()->addDays(2)->setTime(9, 0),
            'ends_at' => now()->addDays(2)->setTime(11, 0),
        ]);
        $overlapping = $this->makeSession([
            'capacity' => 10,
            'title' => 'Overlapping',
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'ends_at' => now()->addDays(2)->setTime(12, 0),
        ]);
        $later = $this->makeSession([
            'capacity' => 10,
            'title' => 'Later',
            'starts_at' => now()->addDays(2)->setTime(13, 0),
            'ends_at' => now()->addDays(2)->setTime(14, 0),
        ]);

        foreach ([$morning, $overlapping, $later] as $sessionId) {
            $this->registration->register($sessionId, $attendeeId);
        }

        $conflicts = $this->conflicts->forAttendee($this->eventId, $attendeeId);

        $this->assertCount(1, $conflicts);
        $this->assertSame($morning, $conflicts->first()['session_id']);
        $this->assertSame($overlapping, $conflicts->first()['conflicts_with']);
    }

    public function test_back_to_back_sessions_do_not_conflict(): void
    {
        $attendeeId = $this->makeAttendee();

        $first = $this->makeSession([
            'capacity' => 10,
            'starts_at' => now()->addDays(2)->setTime(9, 0),
            'ends_at' => now()->addDays(2)->setTime(10, 0),
        ]);
        $second = $this->makeSession([
            'capacity' => 10,
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'ends_at' => now()->addDays(2)->setTime(11, 0),
        ]);

        $this->registration->register($first, $attendeeId);
        $this->registration->register($second, $attendeeId);

        $this->assertCount(
            0,
            $this->conflicts->forAttendee($this->eventId, $attendeeId),
            'A session starting exactly when another ends is not a clash.'
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSession(array $overrides = []): int
    {
        return (int) DB::table('sessions')->insertGetId(array_merge([
            'short_id' => 'ss_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'title' => 'Test Session',
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

    private function makeAttendee(?int $eventId = null): int
    {
        $eventId ??= $this->eventId;

        $productId = (int) DB::table('products')->insertGetId([
            'title' => 'Session Ticket',
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
            'first_name' => 'Session',
            'last_name' => 'Buyer',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('attendees')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(16)),
            'public_id' => 'A-'.Str::upper(Str::random(10)),
            'first_name' => 'Session',
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

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Session Organizer',
            'email' => 'sess-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Session Test Event',
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
}
