<?php

namespace Tests\Feature\Services\Domain\Networking;

use Carbon\CarbonImmutable;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Networking\MeetingService;
use HiEvents\Services\Domain\Networking\NetworkingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NetworkingAndMeetingTest extends TestCase
{
    use DatabaseTransactions;

    private NetworkingService $networking;

    private MeetingService $meetings;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private CarbonImmutable $slotStart;

    private CarbonImmutable $slotEnd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->networking = app(NetworkingService::class);
        $this->meetings = app(MeetingService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent();

        $this->slotStart = CarbonImmutable::parse('2026-10-06 10:00:00', 'UTC');
        $this->slotEnd = CarbonImmutable::parse('2026-10-06 10:30:00', 'UTC');
    }

    // ---------------------------------------------------------------- directory

    public function test_a_person_is_not_discoverable_until_they_opt_in(): void
    {
        $viewer = $this->makePerson();
        $other = $this->makePerson();

        DB::table('networking_profiles')->insert([
            'short_id' => 'np_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'person_id' => $other,
            'is_discoverable' => false,
            'share_contact_on_connect' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertCount(
            0,
            $this->networking->directory($this->eventId, $viewer),
            'A directory listing people who did not ask to be listed is a privacy incident '
            .'whatever the setting is called.'
        );
    }

    public function test_opting_in_makes_a_person_discoverable(): void
    {
        $viewer = $this->makePerson();
        $other = $this->makePerson('Layla', 'Acme Corp');

        $this->networking->optIn($this->eventId, $other, headline: 'Looking for suppliers');

        $directory = $this->networking->directory($this->eventId, $viewer);

        $this->assertCount(1, $directory);
        $this->assertSame('Layla', $directory[0]->first_name);
        $this->assertSame('Looking for suppliers', $directory[0]->headline);
    }

    public function test_opting_in_twice_updates_rather_than_duplicates(): void
    {
        $person = $this->makePerson();

        $first = $this->networking->optIn($this->eventId, $person, headline: 'First');
        $second = $this->networking->optIn($this->eventId, $person, headline: 'Second');

        $this->assertSame($first, $second);
        $this->assertSame(
            'Second',
            DB::table('networking_profiles')->where('id', $first)->value('headline')
        );
    }

    public function test_opting_out_hides_the_profile(): void
    {
        $viewer = $this->makePerson();
        $other = $this->makePerson();

        $this->networking->optIn($this->eventId, $other);
        $this->networking->optOut($this->eventId, $other);

        $this->assertCount(0, $this->networking->directory($this->eventId, $viewer));
        $this->assertNotNull(
            DB::table('networking_profiles')->where('person_id', $other)->value('opted_out_at')
        );
    }

    public function test_a_viewer_does_not_appear_in_their_own_directory(): void
    {
        $viewer = $this->makePerson();
        $this->networking->optIn($this->eventId, $viewer);

        $this->assertCount(0, $this->networking->directory($this->eventId, $viewer));
    }

    public function test_the_directory_can_be_searched(): void
    {
        $viewer = $this->makePerson();
        $this->networking->optIn($this->eventId, $this->makePerson('Layla', 'Acme Corp'));
        $this->networking->optIn($this->eventId, $this->makePerson('Omar', 'Beta Industries'));

        $this->assertCount(1, $this->networking->directory($this->eventId, $viewer, 'acme'));
        $this->assertCount(1, $this->networking->directory($this->eventId, $viewer, 'omar'));
        $this->assertCount(2, $this->networking->directory($this->eventId, $viewer));
    }

    public function test_another_events_directory_is_separate(): void
    {
        $viewer = $this->makePerson();
        $other = $this->makePerson();
        $otherEventId = $this->makeEvent();

        $this->networking->optIn($otherEventId, $other);

        $this->assertCount(
            0,
            $this->networking->directory($this->eventId, $viewer),
            'Opting in to one event is not consent to be listed at another.'
        );
    }

    // ---------------------------------------------------------------- connections

    public function test_a_connection_request_waits_for_an_answer(): void
    {
        [$requester, $recipient] = $this->twoDiscoverablePeople();

        $connectionId = $this->networking->requestConnection(
            $this->eventId,
            $requester,
            $recipient,
            note: 'Met at the keynote'
        );

        $row = DB::table('connections')->where('id', $connectionId)->first();

        $this->assertSame('PENDING', $row->status);
        $this->assertSame('Met at the keynote', $row->note);
        $this->assertNull($row->responded_at);
    }

    public function test_only_the_person_asked_can_answer(): void
    {
        [$requester, $recipient] = $this->twoDiscoverablePeople();

        $connectionId = $this->networking->requestConnection($this->eventId, $requester, $recipient);

        $this->expectExceptionMessageMatches('/received the request/');
        $this->networking->respondToConnection($connectionId, $requester, accept: true);
    }

    public function test_accepting_records_the_answer(): void
    {
        [$requester, $recipient] = $this->twoDiscoverablePeople();

        $connectionId = $this->networking->requestConnection($this->eventId, $requester, $recipient);
        $this->networking->respondToConnection($connectionId, $recipient, accept: true);

        $row = DB::table('connections')->where('id', $connectionId)->first();

        $this->assertSame('ACCEPTED', $row->status);
        $this->assertNotNull($row->responded_at);
    }

    public function test_a_request_cannot_be_answered_twice(): void
    {
        [$requester, $recipient] = $this->twoDiscoverablePeople();

        $connectionId = $this->networking->requestConnection($this->eventId, $requester, $recipient);
        $this->networking->respondToConnection($connectionId, $recipient, accept: false);

        $this->expectExceptionMessageMatches('/already been answered/');
        $this->networking->respondToConnection($connectionId, $recipient, accept: true);
    }

    public function test_two_people_who_both_ask_are_simply_connected(): void
    {
        [$first, $second] = $this->twoDiscoverablePeople();

        $connectionId = $this->networking->requestConnection($this->eventId, $first, $second);
        $reciprocal = $this->networking->requestConnection($this->eventId, $second, $first);

        $this->assertSame(
            $connectionId,
            $reciprocal,
            'A and B both wanting to connect is one connection, not two halves that can '
            .'disagree about their status.'
        );
        $this->assertSame(
            'ACCEPTED',
            DB::table('connections')->where('id', $connectionId)->value('status')
        );
        $this->assertSame(1, DB::table('connections')->where('event_id', $this->eventId)->count());
    }

    public function test_an_already_answered_connection_cannot_be_requested_again(): void
    {
        [$first, $second] = $this->twoDiscoverablePeople();

        $connectionId = $this->networking->requestConnection($this->eventId, $first, $second);
        $this->networking->respondToConnection($connectionId, $second, accept: false);

        $this->expectExceptionMessageMatches('/already exists/');
        $this->networking->requestConnection($this->eventId, $first, $second);
    }

    public function test_nobody_connects_with_themselves(): void
    {
        $person = $this->makePerson();

        $this->expectExceptionMessageMatches('/yourself/');
        $this->networking->requestConnection($this->eventId, $person, $person);
    }

    public function test_a_person_who_never_opted_in_cannot_be_approached(): void
    {
        $requester = $this->makePerson();
        $this->networking->optIn($this->eventId, $requester);
        $private = $this->makePerson();

        $this->expectExceptionMessageMatches('/not accepting connections/');
        $this->networking->requestConnection($this->eventId, $requester, $private);
    }

    public function test_a_badge_scan_connects_without_needing_the_directory(): void
    {
        $scanner = $this->makePerson();
        $scanned = $this->makePerson();

        $connectionId = $this->networking->requestConnection(
            $this->eventId,
            $scanner,
            $scanned,
            source: 'BADGE_SCAN'
        );

        $this->assertSame(
            'ACCEPTED',
            DB::table('connections')->where('id', $connectionId)->value('status'),
            'Two people standing together who chose to scan have consented by doing it; the '
            .'directory opt-in is for being approached out of the blue.'
        );
    }

    public function test_an_overlong_note_is_refused(): void
    {
        [$requester, $recipient] = $this->twoDiscoverablePeople();

        $this->expectExceptionMessageMatches('/limited to 280/');
        $this->networking->requestConnection(
            $this->eventId,
            $requester,
            $recipient,
            note: str_repeat('a', 281)
        );
    }

    // ---------------------------------------------------------------- blocking

    public function test_blocking_hides_both_directions(): void
    {
        [$blocker, $blocked] = $this->twoDiscoverablePeople();

        $this->networking->block($this->eventId, $blocker, $blocked);

        $this->assertCount(0, $this->networking->directory($this->eventId, $blocker));
        $this->assertCount(
            0,
            $this->networking->directory($this->eventId, $blocked),
            'A block that works one way leaves the blocked person still able to see and '
            .'approach the blocker.'
        );
    }

    public function test_a_blocked_person_cannot_send_a_request(): void
    {
        [$blocker, $blocked] = $this->twoDiscoverablePeople();

        $this->networking->block($this->eventId, $blocker, $blocked);

        $this->expectException(ResourceConflictException::class);
        $this->networking->requestConnection($this->eventId, $blocked, $blocker);
    }

    public function test_blocking_closes_an_existing_connection(): void
    {
        [$first, $second] = $this->twoDiscoverablePeople();

        $connectionId = $this->networking->requestConnection($this->eventId, $first, $second);
        $this->networking->respondToConnection($connectionId, $second, accept: true);

        $this->networking->block($this->eventId, $second, $first);

        $this->assertSame(
            'BLOCKED',
            DB::table('connections')->where('id', $connectionId)->value('status')
        );
    }

    public function test_blocking_twice_is_the_same_as_once(): void
    {
        [$blocker, $blocked] = $this->twoDiscoverablePeople();

        $this->networking->block($this->eventId, $blocker, $blocked);
        $this->networking->block($this->eventId, $blocker, $blocked);

        $this->assertSame(1, DB::table('networking_blocks')->where('event_id', $this->eventId)->count());
    }

    public function test_unblocking_restores_visibility(): void
    {
        [$blocker, $blocked] = $this->twoDiscoverablePeople();

        $this->networking->block($this->eventId, $blocker, $blocked);
        $this->networking->unblock($this->eventId, $blocker, $blocked);

        $this->assertCount(1, $this->networking->directory($this->eventId, $blocker));
    }

    // ---------------------------------------------------------------- contact sharing

    public function test_a_connection_hides_the_email_unless_it_was_offered(): void
    {
        $requester = $this->makePerson();
        $this->networking->optIn($this->eventId, $requester);
        $guarded = $this->makePerson();
        $this->networking->optIn($this->eventId, $guarded, shareContactOnConnect: false);

        $connectionId = $this->networking->requestConnection($this->eventId, $requester, $guarded);
        $this->networking->respondToConnection($connectionId, $guarded, accept: true);

        $connections = $this->networking->connectionsFor($this->eventId, $requester);

        $this->assertCount(1, $connections);
        $this->assertNull(
            $connections[0]->email,
            'Accepting a connection is not the same as handing over an email address.'
        );
    }

    public function test_a_connection_shows_the_email_when_it_was_offered(): void
    {
        $requester = $this->makePerson();
        $this->networking->optIn($this->eventId, $requester);
        $open = $this->makePerson();
        $this->networking->optIn($this->eventId, $open, shareContactOnConnect: true);

        $connectionId = $this->networking->requestConnection($this->eventId, $requester, $open);
        $this->networking->respondToConnection($connectionId, $open, accept: true);

        $this->assertNotNull($this->networking->connectionsFor($this->eventId, $requester)[0]->email);
    }

    public function test_a_pending_connection_never_shows_an_email(): void
    {
        $requester = $this->makePerson();
        $this->networking->optIn($this->eventId, $requester);
        $open = $this->makePerson();
        $this->networking->optIn($this->eventId, $open, shareContactOnConnect: true);

        $this->networking->requestConnection($this->eventId, $requester, $open);

        $this->assertNull(
            $this->networking->connectionsFor($this->eventId, $requester)[0]->email,
            'A request that has not been accepted is not consent to anything.'
        );
    }

    // ---------------------------------------------------------------- meetings

    public function test_a_meeting_is_requested_with_the_requester_already_accepted(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $result = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );

        $this->assertSame(
            'REQUESTED',
            DB::table('meetings')->where('id', $result['meeting_id'])->value('status')
        );
        $this->assertSame(
            'ACCEPTED',
            DB::table('meeting_participants')
                ->where('meeting_id', $result['meeting_id'])
                ->where('person_id', $requester)
                ->value('response'),
            'Asking for a meeting is already saying yes to it.'
        );
    }

    public function test_a_meeting_confirms_once_everybody_accepts(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $result = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );

        $this->meetings->respond($result['meeting_id'], $invitee, accept: true);

        $this->assertSame(
            'CONFIRMED',
            DB::table('meetings')->where('id', $result['meeting_id'])->value('status')
        );
    }

    public function test_one_refusal_declines_the_meeting(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $result = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );

        $this->meetings->respond($result['meeting_id'], $invitee, accept: false);

        $this->assertSame(
            'DECLINED',
            DB::table('meetings')->where('id', $result['meeting_id'])->value('status'),
            'A two-person meeting one side declined is not happening, and leaving it requested '
            .'would keep it in both diaries.'
        );
    }

    public function test_a_three_person_meeting_waits_for_the_last_answer(): void
    {
        $requester = $this->makePerson();
        $first = $this->makePerson();
        $second = $this->makePerson();

        $result = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$first, $second],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );

        $this->meetings->respond($result['meeting_id'], $first, accept: true);

        $this->assertSame(
            'REQUESTED',
            DB::table('meetings')->where('id', $result['meeting_id'])->value('status')
        );

        $this->meetings->respond($result['meeting_id'], $second, accept: true);

        $this->assertSame(
            'CONFIRMED',
            DB::table('meetings')->where('id', $result['meeting_id'])->value('status')
        );
    }

    public function test_a_clash_is_only_a_warning_when_requesting(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $first = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );
        $this->meetings->respond($first['meeting_id'], $invitee, accept: true);

        $second = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $this->makePerson(),
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart->addMinutes(10),
            endsAt: $this->slotEnd->addMinutes(10),
        );

        $this->assertNotEmpty(
            $second['clashes'],
            'Somebody may hold two tentative invitations and choose between them, so the '
            .'clash is shown rather than refused.'
        );
        $this->assertSame(
            'REQUESTED',
            DB::table('meetings')->where('id', $second['meeting_id'])->value('status')
        );
    }

    public function test_a_clash_is_a_hard_error_at_confirmation(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $first = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );
        $this->meetings->respond($first['meeting_id'], $invitee, accept: true);

        $second = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $this->makePerson(),
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart->addMinutes(10),
            endsAt: $this->slotEnd->addMinutes(10),
        );

        $this->expectExceptionMessageMatches('/double-book/');
        $this->meetings->respond($second['meeting_id'], $invitee, accept: true);
    }

    public function test_back_to_back_meetings_are_not_a_clash(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $first = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );
        $this->meetings->respond($first['meeting_id'], $invitee, accept: true);

        $second = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $this->makePerson(),
            inviteePersonIds: [$invitee],
            startsAt: $this->slotEnd,
            endsAt: $this->slotEnd->addMinutes(30),
        );

        $this->assertEmpty(
            $second['clashes'],
            'A meeting ending exactly when the next begins is back-to-back, not a clash.'
        );

        $this->meetings->respond($second['meeting_id'], $invitee, accept: true);

        $this->assertSame(
            'CONFIRMED',
            DB::table('meetings')->where('id', $second['meeting_id'])->value('status')
        );
    }

    public function test_a_declined_meeting_does_not_block_the_slot(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $first = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );
        $this->meetings->respond($first['meeting_id'], $invitee, accept: false);

        $second = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $this->makePerson(),
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );
        $this->meetings->respond($second['meeting_id'], $invitee, accept: true);

        $this->assertSame(
            'CONFIRMED',
            DB::table('meetings')->where('id', $second['meeting_id'])->value('status')
        );
    }

    public function test_a_meeting_must_end_after_it_starts(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $this->expectExceptionMessageMatches('/must end after it starts/');
        $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotEnd,
            endsAt: $this->slotStart,
        );
    }

    public function test_a_meeting_needs_somebody_else(): void
    {
        $requester = $this->makePerson();

        $this->expectExceptionMessageMatches('/at least one other person/');
        $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$requester],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );
    }

    public function test_somebody_not_invited_cannot_answer(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $result = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );

        $this->expectExceptionMessageMatches('/not invited/');
        $this->meetings->respond($result['meeting_id'], $this->makePerson(), accept: true);
    }

    public function test_a_cancelled_meeting_cannot_be_answered(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $result = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );

        $this->meetings->cancel($result['meeting_id'], $this->eventId);

        $this->expectExceptionMessageMatches('/no longer be answered/');
        $this->meetings->respond($result['meeting_id'], $invitee, accept: true);
    }

    public function test_only_a_confirmed_meeting_can_be_closed_out(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $result = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );

        $this->expectExceptionMessageMatches('/Only a confirmed meeting/');
        $this->meetings->recordOutcome($result['meeting_id'], $this->eventId, attended: true);
    }

    public function test_a_no_show_is_recorded_separately_from_completion(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $result = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );
        $this->meetings->respond($result['meeting_id'], $invitee, accept: true);

        $this->meetings->recordOutcome($result['meeting_id'], $this->eventId, attended: false);

        $this->assertSame(
            'NO_SHOW',
            DB::table('meetings')->where('id', $result['meeting_id'])->value('status'),
            'An exhibitor renewing a stand wants to know how many booked meetings nobody '
            .'turned up to.'
        );
    }

    public function test_cancelling_another_events_meeting_is_refused(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $result = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );

        $this->expectExceptionMessageMatches('/could not be cancelled/');
        $this->meetings->cancel($result['meeting_id'], $this->makeEvent());
    }

    public function test_a_schedule_lists_a_persons_meetings_in_order(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();

        $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart->addHours(2),
            endsAt: $this->slotEnd->addHours(2),
        );
        $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
        );

        $schedule = $this->meetings->scheduleFor($this->eventId, $invitee);

        $this->assertCount(2, $schedule);
        $this->assertTrue(
            CarbonImmutable::parse((string) $schedule[0]->starts_at)
                ->lessThan(CarbonImmutable::parse((string) $schedule[1]->starts_at))
        );
        $this->assertSame('INVITEE', $schedule[0]->role);
    }

    public function test_an_exhibitor_meeting_records_the_exhibitor(): void
    {
        [$requester, $invitee] = $this->twoDiscoverablePeople();
        $exhibitorId = $this->makeExhibitor();

        $result = $this->meetings->request(
            eventId: $this->eventId,
            requesterPersonId: $requester,
            inviteePersonIds: [$invitee],
            startsAt: $this->slotStart,
            endsAt: $this->slotEnd,
            eventExhibitorId: $exhibitorId,
            locationLabel: 'Table 14, Business Lounge',
        );

        $row = DB::table('meetings')->where('id', $result['meeting_id'])->first();

        $this->assertSame($exhibitorId, (int) $row->event_exhibitor_id);
        $this->assertSame('Table 14, Business Lounge', $row->location_label);
    }

    // ---------------------------------------------------------------- fixtures

    /**
     * @return array{0: int, 1: int}
     */
    private function twoDiscoverablePeople(): array
    {
        $first = $this->makePerson();
        $second = $this->makePerson();

        $this->networking->optIn($this->eventId, $first);
        $this->networking->optIn($this->eventId, $second);

        return [$first, $second];
    }

    private function makeExhibitor(): int
    {
        $companyId = (int) DB::table('companies')->insertGetId([
            'short_id' => 'co_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Meeting Exhibitor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('event_exhibitors')->insertGetId([
            'short_id' => 'ee_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'company_id' => $companyId,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePerson(string $firstName = 'Networker', ?string $company = null): int
    {
        return (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => $firstName,
            'last_name' => Str::upper(Str::random(5)),
            'company' => $company,
            'email' => Str::lower(Str::random(12)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Networking Organizer',
            'email' => 'nw-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Networking Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(6),
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
