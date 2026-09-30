<?php

namespace Tests\Feature\Services\Domain\Rsvp;

use HiEvents\DomainObjects\Enums\LeadRating;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Exhibitor\LeadScoringService;
use HiEvents\Services\Domain\Rsvp\RsvpService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RsvpAndScoringTest extends TestCase
{
    use DatabaseTransactions;

    private RsvpService $rsvp;

    private LeadScoringService $scoring;

    private int $accountId;

    private int $userId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rsvp = app(RsvpService::class);
        $this->scoring = app(LeadScoringService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent();
    }

    // ---------------------------------------------------------------- RSVP

    public function test_an_invitation_token_is_stored_only_as_a_hash(): void
    {
        $invited = $this->rsvp->invite($this->eventId, 'Layla', 'layla@example.test');

        $row = DB::table('invitations')->where('id', $invited['invitation_id'])->first();

        $this->assertSame(hash('sha256', $invited['token']), $row->token_hash);
        $this->assertSame(
            0,
            DB::table('invitations')->where('token_hash', $invited['token'])->count(),
            'Whoever holds the token can answer for the invitee, so a backup must not contain it.'
        );
    }

    public function test_a_guest_confirms_for_a_party(): void
    {
        $invited = $this->rsvp->invite($this->eventId, 'Layla', 'l@example.test', maxPartySize: 4);

        $result = $this->rsvp->respond($invited['token'], 'ATTENDING', partySize: 3);

        $this->assertSame('ATTENDING', $result->response);
        $this->assertSame(3, $result->partySize);
        $this->assertSame(
            'ATTENDING',
            DB::table('invitations')->where('id', $invited['invitation_id'])->value('status')
        );
    }

    public function test_accepting_beyond_the_allowance_is_refused_rather_than_trimmed(): void
    {
        $invited = $this->rsvp->invite($this->eventId, 'Layla', 'l@example.test', maxPartySize: 2);

        $this->expectExceptionMessageMatches('/allows at most 2 guest/');
        $this->rsvp->respond($invited['token'], 'ATTENDING', partySize: 5);
    }

    public function test_declining_brings_nobody_whatever_the_party_size_says(): void
    {
        $invited = $this->rsvp->invite($this->eventId, 'Layla', 'l@example.test', maxPartySize: 4);

        $result = $this->rsvp->respond($invited['token'], 'NOT_ATTENDING', partySize: 3);

        $this->assertSame(0, $result->partySize);
    }

    public function test_a_guest_may_change_their_mind(): void
    {
        $invited = $this->rsvp->invite($this->eventId, 'Layla', 'l@example.test', maxPartySize: 4);

        $this->rsvp->respond($invited['token'], 'ATTENDING', partySize: 2);
        $changed = $this->rsvp->respond($invited['token'], 'NOT_ATTENDING');

        $this->assertTrue($changed->isChange());
        $this->assertSame('ATTENDING', $changed->changedFrom);
        $this->assertSame(
            1,
            DB::table('rsvp_responses')->where('invitation_id', $invited['invitation_id'])->count(),
            'The guest list should read as the latest answer, not a history to interpret.'
        );
    }

    public function test_an_expired_invitation_is_refused(): void
    {
        $invited = $this->rsvp->invite($this->eventId, 'Layla', 'l@example.test');

        DB::table('invitations')
            ->where('id', $invited['invitation_id'])
            ->update(['expires_at' => now()->subDay()]);

        $this->expectExceptionMessageMatches('/has expired/');
        $this->rsvp->respond($invited['token'], 'ATTENDING');
    }

    public function test_an_unknown_token_is_refused(): void
    {
        $this->expectExceptionMessageMatches('/could not be found/');
        $this->rsvp->respond('not-a-real-token', 'ATTENDING');
    }

    public function test_an_invalid_response_is_refused(): void
    {
        $invited = $this->rsvp->invite($this->eventId, 'Layla', 'l@example.test');

        $this->expectExceptionMessageMatches('/not a valid response/');
        $this->rsvp->respond($invited['token'], 'MAYBE_LATER');
    }

    public function test_the_same_email_cannot_be_invited_twice(): void
    {
        $this->rsvp->invite($this->eventId, 'Layla', 'layla@example.test');

        $this->expectExceptionMessageMatches('/already been invited/');
        $this->rsvp->invite($this->eventId, 'Layla', 'LAYLA@example.test');
    }

    public function test_revoking_an_invitation_kills_its_link(): void
    {
        $invited = $this->rsvp->invite($this->eventId, 'Layla', 'l@example.test');

        $this->rsvp->revoke($invited['invitation_id'], $this->eventId);

        $this->expectException(ResourceConflictException::class);
        $this->rsvp->respond($invited['token'], 'ATTENDING');
    }

    public function test_the_summary_counts_guests_not_replies(): void
    {
        $first = $this->rsvp->invite($this->eventId, 'A', 'a@example.test', maxPartySize: 4);
        $second = $this->rsvp->invite($this->eventId, 'B', 'b@example.test', maxPartySize: 4);
        $third = $this->rsvp->invite($this->eventId, 'C', 'c@example.test', maxPartySize: 4);
        $this->rsvp->invite($this->eventId, 'D', 'd@example.test');

        $this->rsvp->respond($first['token'], 'ATTENDING', partySize: 3);
        $this->rsvp->respond($second['token'], 'ATTENDING', partySize: 2);
        $this->rsvp->respond($third['token'], 'NOT_ATTENDING');

        $summary = $this->rsvp->summary($this->eventId);

        $this->assertSame(4, $summary['invited']);
        $this->assertSame(2, $summary['attending']);
        $this->assertSame(1, $summary['not_attending']);
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(
            5,
            $summary['expected_guests'],
            'Two acceptances can mean five people through the door.'
        );
    }

    public function test_tentative_guests_are_counted_separately(): void
    {
        $invited = $this->rsvp->invite($this->eventId, 'A', 'a@example.test', maxPartySize: 4);

        $this->rsvp->respond($invited['token'], 'TENTATIVE', partySize: 3);

        $summary = $this->rsvp->summary($this->eventId);

        $this->assertSame(1, $summary['tentative']);
        $this->assertSame(3, $summary['tentative_guests']);
        $this->assertSame(
            0,
            $summary['expected_guests'],
            'Catering planned against maybes overstates the room.'
        );
    }

    public function test_an_invitation_must_allow_at_least_one_guest(): void
    {
        $this->expectExceptionMessageMatches('/at least one guest/');
        $this->rsvp->invite($this->eventId, 'A', 'a@example.test', maxPartySize: 0);
    }

    // ---------------------------------------------------------------- lead scoring

    public function test_a_hot_rated_lead_scores_higher_than_a_cold_one(): void
    {
        $hot = $this->makeLead(rating: LeadRating::HOT);
        $cold = $this->makeLead(rating: LeadRating::COLD);

        $this->assertGreaterThan(
            $this->scoring->score($cold)['score'],
            $this->scoring->score($hot)['score']
        );
    }

    public function test_an_unrated_lead_is_not_treated_as_cold(): void
    {
        $unrated = $this->makeLead(rating: null);
        $cold = $this->makeLead(rating: LeadRating::COLD);

        $this->assertGreaterThan(
            $this->scoring->score($cold)['score'],
            $this->scoring->score($unrated)['score'],
            'A busy booth rates nobody; treating that as negative buries its leads.'
        );
    }

    public function test_revisits_raise_the_score_but_saturate(): void
    {
        $once = $this->makeLead(captureCount: 1);
        $thrice = $this->makeLead(captureCount: 3);
        $many = $this->makeLead(captureCount: 9);

        $onceScore = $this->scoring->score($once)['score'];
        $thriceScore = $this->scoring->score($thrice)['score'];
        $manyScore = $this->scoring->score($many)['score'];

        $this->assertGreaterThan($onceScore, $thriceScore);
        $this->assertLessThanOrEqual(
            $thriceScore + 4,
            $manyScore,
            'A fifth visit does not mean five times the interest.'
        );
    }

    public function test_notes_and_qualification_answers_count_as_engagement(): void
    {
        $bare = $this->makeLead();
        $engaged = $this->makeLead(notes: 'Wants a quote for 200 units', qualification: ['budget' => 'confirmed']);

        $this->assertGreaterThan(
            $this->scoring->score($bare)['score'],
            $this->scoring->score($engaged)['score']
        );
    }

    public function test_the_score_is_capped(): void
    {
        $leadId = $this->makeLead(
            rating: LeadRating::HOT,
            captureCount: 12,
            notes: 'Very interested',
            qualification: ['budget' => 'yes', 'timeline' => 'Q1'],
        );

        $this->assertLessThanOrEqual(100, $this->scoring->score($leadId)['score']);
    }

    public function test_every_component_is_explained(): void
    {
        $scored = $this->scoring->score($this->makeLead(rating: LeadRating::WARM));

        $factors = array_column($scored['breakdown'], 'factor');

        $this->assertContains('staff_rating', $factors);
        $this->assertContains('revisits', $factors);
        $this->assertContains('engagement', $factors);
        $this->assertContains('profile_completeness', $factors);
        $this->assertContains('programme_engagement', $factors);

        foreach ($scored['breakdown'] as $component) {
            $this->assertNotSame(
                '',
                $component['detail'],
                'A score nobody can explain gets ignored, which is worse than no score.'
            );
        }
    }

    public function test_a_score_falls_into_a_band(): void
    {
        $priority = $this->scoring->score($this->makeLead(
            rating: LeadRating::HOT,
            captureCount: 4,
            notes: 'Ready to buy',
            qualification: ['budget' => 'confirmed'],
        ));
        $nurture = $this->scoring->score($this->makeLead(rating: LeadRating::COLD, withProfile: false));

        $this->assertSame('PRIORITY', $priority['band']);
        $this->assertSame('NURTURE', $nurture['band']);
    }

    public function test_the_profile_component_reads_the_capture_snapshot(): void
    {
        $leadId = $this->makeLead(withProfile: true);

        $before = $this->scoring->score($leadId)['score'];

        // Editing the live person row must not change a score derived from what the
        // exhibitor actually received.
        $personId = (int) DB::table('leads')->where('id', $leadId)->value('person_id');
        DB::table('persons')->where('id', $personId)->update(['company' => null, 'job_title' => null]);

        $this->assertSame($before, $this->scoring->score($leadId)['score']);
    }

    public function test_leads_are_ranked_highest_first(): void
    {
        $exhibitorId = $this->makeExhibitor();

        $this->makeLead(rating: LeadRating::COLD, exhibitorId: $exhibitorId, withProfile: false);
        $this->makeLead(rating: LeadRating::HOT, exhibitorId: $exhibitorId, captureCount: 3);

        $ranked = $this->scoring->rankForExhibitor($exhibitorId);

        $this->assertCount(2, $ranked);
        $this->assertGreaterThan($ranked[1]['score'], $ranked[0]['score']);
    }

    public function test_an_unknown_lead_scores_nothing(): void
    {
        $this->assertNull($this->scoring->score(99999999));
    }

    // ---------------------------------------------------------------- fixtures

    private function makeLead(
        ?LeadRating $rating = null,
        int $captureCount = 1,
        ?string $notes = null,
        ?array $qualification = null,
        ?int $exhibitorId = null,
        bool $withProfile = true,
    ): int {
        $personId = (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Scored',
            'last_name' => 'Lead',
            'company' => $withProfile ? 'Buyer Co' : null,
            'job_title' => $withProfile ? 'Head of Procurement' : null,
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('leads')->insertGetId([
            'short_id' => 'ld_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'event_exhibitor_id' => $exhibitorId ?? $this->makeExhibitor(),
            'person_id' => $personId,
            'shared_fields' => json_encode($withProfile
                ? ['first_name' => 'Scored', 'company' => 'Buyer Co', 'job_title' => 'Head of Procurement', 'email' => 'x@test.local']
                : ['first_name' => 'Scored']),
            'first_captured_at' => now(),
            'last_captured_at' => now(),
            'capture_count' => $captureCount,
            'rating' => $rating?->value,
            'notes' => $notes,
            'qualification' => $qualification !== null ? json_encode($qualification) : null,
            'status' => 'NEW',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeExhibitor(): int
    {
        $companyId = (int) DB::table('companies')->insertGetId([
            'short_id' => 'co_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Exhibitor '.Str::random(6),
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

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'RSVP Organizer',
            'email' => 'rsvp-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'RSVP Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(20),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
