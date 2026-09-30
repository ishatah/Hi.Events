<?php

namespace Tests\Feature\Services\Domain\Sponsor;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Sponsor\SponsorshipService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SponsorshipServiceTest extends TestCase
{
    use DatabaseTransactions;

    private SponsorshipService $service;

    private int $accountId;

    private int $userId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SponsorshipService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent();
    }

    public function test_a_package_instantiates_its_entitlements_onto_the_sponsorship(): void
    {
        $packageId = $this->service->createPackage(
            eventId: $this->eventId,
            name: 'Platinum',
            tier: 'PLATINUM',
            price: 50000.0,
            currency: 'QAR',
            entitlements: [
                ['type' => 'GUEST_PASSES', 'quantity' => 20, 'description' => '20 complimentary passes'],
                ['type' => 'LOGO_PLACEMENT', 'quantity' => 1],
            ],
        );

        $sponsorshipId = $this->service->createSponsorship(
            eventId: $this->eventId,
            companyId: $this->makeCompany(),
            tier: 'PLATINUM',
            packageId: $packageId,
        );

        $entitlements = DB::table('sponsorship_entitlements')
            ->where('sponsorship_id', $sponsorshipId)
            ->orderBy('entitlement_type')
            ->get();

        $this->assertCount(2, $entitlements);
        $this->assertSame('GUEST_PASSES', $entitlements[0]->entitlement_type);
        $this->assertSame(20, (int) $entitlements[0]->quantity);
    }

    public function test_the_sponsorship_keeps_what_was_agreed_not_what_was_advertised(): void
    {
        $packageId = $this->service->createPackage(
            eventId: $this->eventId,
            name: 'Gold',
            tier: 'GOLD',
            price: 30000.0,
            entitlements: [['type' => 'GUEST_PASSES', 'quantity' => 10]],
        );

        $sponsorshipId = $this->service->createSponsorship(
            eventId: $this->eventId,
            companyId: $this->makeCompany(),
            tier: 'GOLD',
            packageId: $packageId,
            contractValue: 22000.0,
        );

        $entitlementId = (int) DB::table('sponsorship_entitlements')
            ->where('sponsorship_id', $sponsorshipId)
            ->value('id');

        DB::table('sponsorship_entitlements')->where('id', $entitlementId)->update(['quantity' => 14]);

        $this->assertSame(
            '22000.00',
            DB::table('sponsorships')->where('id', $sponsorshipId)->value('contract_value'),
            'The package is a price list; the sponsorship is the deal that was struck.'
        );
        $this->assertSame(
            '30000.00',
            DB::table('sponsorship_packages')->where('id', $packageId)->value('price'),
            'Negotiating a deal down must not rewrite the price list.'
        );
        $this->assertSame(14, $this->service->fulfilmentSummary($sponsorshipId)['promised_units']);
    }

    public function test_a_company_cannot_sponsor_the_same_event_twice(): void
    {
        $companyId = $this->makeCompany();

        $this->service->createSponsorship($this->eventId, $companyId, 'GOLD');

        $this->expectExceptionMessageMatches('/already sponsors/');
        $this->service->createSponsorship($this->eventId, $companyId, 'SILVER');
    }

    public function test_a_limited_package_stops_taking_sponsors_when_full(): void
    {
        $packageId = $this->service->createPackage(
            eventId: $this->eventId,
            name: 'Title',
            tier: 'TITLE',
            maxSponsors: 1,
        );

        $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'TITLE', $packageId);

        $this->expectExceptionMessageMatches('/is full/');
        $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'TITLE', $packageId);
    }

    public function test_a_cancelled_sponsorship_frees_its_package_slot(): void
    {
        $packageId = $this->service->createPackage(
            eventId: $this->eventId,
            name: 'Title',
            tier: 'TITLE',
            maxSponsors: 1,
        );

        $first = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'TITLE', $packageId);
        $this->service->transitionStatus($first, $this->eventId, 'CANCELLED');

        $second = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'TITLE', $packageId);

        $this->assertGreaterThan(0, $second);
    }

    public function test_a_package_with_an_unknown_entitlement_type_is_refused(): void
    {
        $this->expectExceptionMessageMatches('/not a known entitlement type/');

        $this->service->createPackage(
            eventId: $this->eventId,
            name: 'Broken',
            tier: 'GOLD',
            entitlements: [['type' => 'FREE_PONY', 'quantity' => 1]],
        );
    }

    public function test_a_package_referencing_another_event_is_refused(): void
    {
        $otherEventId = $this->makeEvent();
        $packageId = $this->service->createPackage($otherEventId, 'Gold', 'GOLD');

        $this->expectExceptionMessageMatches('/could not be found/');
        $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD', $packageId);
    }

    // ---------------------------------------------------------------- fulfilment

    public function test_partial_delivery_reads_as_in_progress(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');
        $entitlementId = $this->service->addEntitlement($sponsorshipId, 'GUEST_PASSES', 10);

        $this->service->recordFulfilment($entitlementId, 4);

        $summary = $this->service->fulfilmentSummary($sponsorshipId);

        $this->assertSame('IN_PROGRESS', $summary['entitlements'][0]['status']);
        $this->assertSame(4, $summary['delivered_units']);
        $this->assertSame(10, $summary['promised_units']);
        $this->assertSame(0.4, $summary['completion']);
    }

    public function test_full_delivery_closes_the_entitlement(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');
        $entitlementId = $this->service->addEntitlement($sponsorshipId, 'GUEST_PASSES', 3);

        $this->service->recordFulfilment($entitlementId, 3);

        $row = DB::table('sponsorship_entitlements')->where('id', $entitlementId)->first();

        $this->assertSame('FULFILLED', $row->status);
        $this->assertNotNull($row->fulfilled_at);
        $this->assertSame(0, $this->service->fulfilmentSummary($sponsorshipId)['outstanding_entitlements']);
    }

    public function test_delivering_more_than_promised_is_refused(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');
        $entitlementId = $this->service->addEntitlement($sponsorshipId, 'GUEST_PASSES', 5);

        $this->service->recordFulfilment($entitlementId, 4);

        $this->expectExceptionMessageMatches('/would deliver 7 of 5/');
        $this->service->recordFulfilment($entitlementId, 3);
    }

    public function test_evidence_accumulates_rather_than_overwriting(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');
        $entitlementId = $this->service->addEntitlement($sponsorshipId, 'LOGO_PLACEMENT', 3);

        $this->service->recordFulfilment($entitlementId, 1, ['placement' => 'event page']);
        $this->service->recordFulfilment($entitlementId, 1, ['placement' => 'badge']);

        $evidence = json_decode(
            (string) DB::table('sponsorship_entitlements')->where('id', $entitlementId)->value('evidence'),
            true
        );

        $this->assertCount(
            2,
            $evidence,
            'Three placements on three dates are three pieces of proof; overwriting would '
            .'leave the last one looking like the whole story.'
        );
        $this->assertSame('event page', $evidence[0]['placement']);
        $this->assertArrayHasKey('recorded_at', $evidence[1]);
    }

    public function test_a_waived_entitlement_is_settled_not_outstanding(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');
        $entitlementId = $this->service->addEntitlement($sponsorshipId, 'SOCIAL_POST', 4);

        $this->service->waiveEntitlement($entitlementId, 'Sponsor declined social coverage');

        $summary = $this->service->fulfilmentSummary($sponsorshipId);

        $this->assertSame(0, $summary['outstanding_entitlements']);
        $this->assertSame(1, $summary['waived_entitlements']);
        $this->assertSame(
            0,
            $summary['promised_units'],
            'A waived promise is not still owed, so counting it would overstate what is due.'
        );
    }

    public function test_a_waived_entitlement_cannot_then_be_fulfilled(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');
        $entitlementId = $this->service->addEntitlement($sponsorshipId, 'SOCIAL_POST', 1);

        $this->service->waiveEntitlement($entitlementId, 'Not wanted');

        $this->expectExceptionMessageMatches('/was waived/');
        $this->service->recordFulfilment($entitlementId, 1);
    }

    public function test_a_delivered_entitlement_cannot_be_waived(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');
        $entitlementId = $this->service->addEntitlement($sponsorshipId, 'BOOTH', 1);

        $this->service->recordFulfilment($entitlementId, 1);

        $this->expectExceptionMessageMatches('/already delivered/');
        $this->service->waiveEntitlement($entitlementId, 'Changed our minds');
    }

    public function test_an_overdue_entitlement_is_flagged(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');
        $entitlementId = $this->service->addEntitlement(
            $sponsorshipId,
            'LOGO_PLACEMENT',
            1,
            dueAt: now()->subWeek()->toDateTimeString()
        );

        $summary = $this->service->fulfilmentSummary($sponsorshipId);

        $this->assertTrue($summary['entitlements'][0]['is_overdue']);
        $this->assertSame($entitlementId, $summary['entitlements'][0]['entitlement_id']);
    }

    public function test_a_delivered_entitlement_is_never_overdue(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');
        $entitlementId = $this->service->addEntitlement(
            $sponsorshipId,
            'LOGO_PLACEMENT',
            1,
            dueAt: now()->subWeek()->toDateTimeString()
        );

        $this->service->recordFulfilment($entitlementId, 1);

        $this->assertFalse($this->service->fulfilmentSummary($sponsorshipId)['entitlements'][0]['is_overdue']);
    }

    public function test_entitlements_that_the_platform_can_prove_are_marked_as_such(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');
        $this->service->addEntitlement($sponsorshipId, 'GUEST_PASSES', 10);
        $this->service->addEntitlement($sponsorshipId, 'SOCIAL_POST', 2);

        $byType = collect($this->service->fulfilmentSummary($sponsorshipId)['entitlements'])
            ->keyBy('entitlement_type');

        $this->assertTrue($byType['GUEST_PASSES']['has_system_evidence']);
        $this->assertFalse(
            $byType['SOCIAL_POST']['has_system_evidence'],
            'A social post needs somebody to attach proof; a redeemed pass does not.'
        );
    }

    public function test_a_sponsorship_with_no_entitlements_has_no_completion_figure(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');

        $this->assertNull(
            $this->service->fulfilmentSummary($sponsorshipId)['completion'],
            '0% delivered and nothing promised are different things.'
        );
    }

    public function test_an_unknown_entitlement_type_is_refused(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');

        $this->expectExceptionMessageMatches('/not a known entitlement type/');
        $this->service->addEntitlement($sponsorshipId, 'FREE_PONY', 1);
    }

    // ---------------------------------------------------------------- public display

    public function test_a_proposed_sponsor_cannot_be_shown_publicly(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');

        $this->expectException(ResourceConflictException::class);
        $this->service->setPublicDisplay($sponsorshipId, $this->eventId, true);
    }

    public function test_a_contracted_sponsor_appears_on_the_public_strip(): void
    {
        $sponsorshipId = $this->service->createSponsorship(
            $this->eventId,
            $this->makeCompany('Qatar Energy'),
            'PLATINUM'
        );

        $this->service->transitionStatus($sponsorshipId, $this->eventId, 'CONTRACTED');
        $this->service->setPublicDisplay($sponsorshipId, $this->eventId, true, 1);

        $sponsors = $this->service->publicSponsors($this->eventId);

        $this->assertCount(1, $sponsors);
        $this->assertSame('Qatar Energy', $sponsors[0]->name);
        $this->assertSame('PLATINUM', $sponsors[0]->tier);
    }

    public function test_a_display_name_overrides_the_company_name(): void
    {
        $sponsorshipId = $this->service->createSponsorship(
            eventId: $this->eventId,
            companyId: $this->makeCompany('Qatar Energy LLC'),
            tier: 'GOLD',
            displayName: 'QatarEnergy',
        );

        $this->service->transitionStatus($sponsorshipId, $this->eventId, 'ACTIVE');
        $this->service->setPublicDisplay($sponsorshipId, $this->eventId, true);

        $this->assertSame('QatarEnergy', $this->service->publicSponsors($this->eventId)[0]->name);
    }

    public function test_cancelling_removes_a_sponsor_from_the_public_page(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');

        $this->service->transitionStatus($sponsorshipId, $this->eventId, 'ACTIVE');
        $this->service->setPublicDisplay($sponsorshipId, $this->eventId, true);
        $this->service->transitionStatus($sponsorshipId, $this->eventId, 'CANCELLED');

        $this->assertCount(
            0,
            $this->service->publicSponsors($this->eventId),
            'A sponsor whose deal fell through must come off the page with the status change, '
            .'not when somebody remembers the second switch.'
        );
        $this->assertFalse(
            (bool) DB::table('sponsorships')->where('id', $sponsorshipId)->value('show_on_event_page')
        );
    }

    public function test_the_public_strip_respects_the_organizers_order(): void
    {
        foreach ([['Third', 3], ['First', 1], ['Second', 2]] as [$name, $order]) {
            $id = $this->service->createSponsorship($this->eventId, $this->makeCompany($name), 'GOLD');
            $this->service->transitionStatus($id, $this->eventId, 'ACTIVE');
            $this->service->setPublicDisplay($id, $this->eventId, true, $order);
        }

        $this->assertSame(
            ['First', 'Second', 'Third'],
            $this->service->publicSponsors($this->eventId)->pluck('name')->all()
        );
    }

    public function test_another_events_sponsors_are_not_listed(): void
    {
        $otherEventId = $this->makeEvent();
        $id = $this->service->createSponsorship($otherEventId, $this->makeCompany('Elsewhere'), 'GOLD');
        $this->service->transitionStatus($id, $otherEventId, 'ACTIVE');
        $this->service->setPublicDisplay($id, $otherEventId, true);

        $this->assertCount(0, $this->service->publicSponsors($this->eventId));
    }

    public function test_transitioning_another_events_sponsorship_is_refused(): void
    {
        $otherEventId = $this->makeEvent();
        $id = $this->service->createSponsorship($otherEventId, $this->makeCompany(), 'GOLD');

        $this->expectExceptionMessageMatches('/could not be found/');
        $this->service->transitionStatus($id, $this->eventId, 'ACTIVE');
    }

    public function test_an_invalid_status_is_refused(): void
    {
        $sponsorshipId = $this->service->createSponsorship($this->eventId, $this->makeCompany(), 'GOLD');

        $this->expectExceptionMessageMatches('/not a valid sponsorship status/');
        $this->service->transitionStatus($sponsorshipId, $this->eventId, 'NEGOTIATING');
    }

    // ---------------------------------------------------------------- fixtures

    private function makeCompany(string $name = 'Sponsor Co'): int
    {
        return (int) DB::table('companies')->insertGetId([
            'short_id' => 'co_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Sponsor Organizer',
            'email' => 'sp-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Sponsored Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(30),
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
