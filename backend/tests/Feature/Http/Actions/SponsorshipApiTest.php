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

class SponsorshipApiTest extends TestCase
{
    use AuthenticatesApiRequests;
    use DatabaseTransactions;

    private string $token;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $foreignEventId;

    protected function setUp(): void
    {
        parent::setUp();

        app(RoleSeedService::class)->seedSystemRoles();

        [$this->token, $this->accountId, $this->userId] = $this->makeTenant();
        $this->eventId = $this->makeEvent($this->accountId, $this->userId);

        [, $foreignAccountId, $foreignUserId] = $this->makeTenant();
        $this->foreignEventId = $this->makeEvent($foreignAccountId, $foreignUserId);
    }

    public function test_the_full_sponsor_journey_over_http(): void
    {
        $packageId = $this->postJson(
            "/events/{$this->eventId}/sponsorship-packages",
            [
                'name' => 'Platinum',
                'tier' => 'PLATINUM',
                'price' => 50000,
                'currency' => 'QAR',
                'entitlements' => [
                    ['type' => 'GUEST_PASSES', 'quantity' => 20],
                    ['type' => 'LOGO_PLACEMENT', 'quantity' => 1],
                ],
            ],
            $this->authHeaders($this->token)
        )->assertStatus(201)->json('id');

        $sponsorshipId = $this->postJson(
            "/events/{$this->eventId}/sponsorships",
            [
                'company_id' => $this->makeCompany('Qatar Energy'),
                'tier' => 'PLATINUM',
                'sponsorship_package_id' => $packageId,
                'contract_value' => 42000,
                'currency' => 'QAR',
            ],
            $this->authHeaders($this->token)
        )->assertStatus(201)->json('id');

        $this->getJson(
            "/events/{$this->eventId}/sponsorships/{$sponsorshipId}/fulfilment",
            $this->authHeaders($this->token)
        )->assertOk()
            ->assertJsonPath('promised_units', 21)
            ->assertJsonPath('delivered_units', 0)
            ->assertJsonPath('completion', 0);

        $this->patchJson(
            "/events/{$this->eventId}/sponsorships/{$sponsorshipId}",
            ['status' => 'CONTRACTED', 'show_on_event_page' => true, 'display_order' => 1],
            $this->authHeaders($this->token)
        )->assertOk();

        $this->getJson("/public/events/{$this->eventId}/sponsors")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Qatar Energy')
            ->assertJsonPath('data.0.tier', 'PLATINUM');
    }

    public function test_the_public_strip_never_carries_commercial_detail(): void
    {
        $sponsorshipId = $this->createContractedSponsorship(contractValue: 99999);

        $response = $this->getJson("/public/events/{$this->eventId}/sponsors")->assertOk();

        $body = $response->getContent();

        foreach (['99999', 'contract_value', 'payment_status', 'PROPOSED'] as $leak) {
            $this->assertStringNotContainsString(
                $leak,
                $body,
                'A negotiated fee on an attendee-facing page is a commercial leak.'
            );
        }

        $this->assertSame(
            ['short_id', 'name', 'tier', 'website_url', 'logo_path'],
            array_keys($response->json('data.0'))
        );
        $this->assertGreaterThan(0, $sponsorshipId);
    }

    public function test_a_proposed_sponsor_is_absent_from_the_public_strip(): void
    {
        $this->postJson(
            "/events/{$this->eventId}/sponsorships",
            ['company_id' => $this->makeCompany('Unsigned Co'), 'tier' => 'GOLD'],
            $this->authHeaders($this->token)
        )->assertStatus(201);

        $this->getJson("/public/events/{$this->eventId}/sponsors")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_showing_a_proposed_sponsor_publicly_is_refused(): void
    {
        $sponsorshipId = $this->postJson(
            "/events/{$this->eventId}/sponsorships",
            ['company_id' => $this->makeCompany(), 'tier' => 'GOLD'],
            $this->authHeaders($this->token)
        )->json('id');

        $this->patchJson(
            "/events/{$this->eventId}/sponsorships/{$sponsorshipId}",
            ['show_on_event_page' => true],
            $this->authHeaders($this->token)
        )->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_fulfilment_is_recorded_with_evidence(): void
    {
        $sponsorshipId = $this->createContractedSponsorship();

        $entitlementId = $this->postJson(
            "/events/{$this->eventId}/sponsorships/{$sponsorshipId}/entitlements",
            ['entitlement_type' => 'LOGO_PLACEMENT', 'quantity' => 2],
            $this->authHeaders($this->token)
        )->assertStatus(201)->json('id');

        $this->postJson(
            "/events/{$this->eventId}/sponsorship-entitlements/{$entitlementId}/fulfilment",
            ['quantity' => 1, 'evidence' => ['placement' => 'event page']],
            $this->authHeaders($this->token)
        )->assertOk()->assertJsonPath('recorded', true);

        $this->getJson(
            "/events/{$this->eventId}/sponsorships/{$sponsorshipId}/fulfilment",
            $this->authHeaders($this->token)
        )->assertOk()
            ->assertJsonPath('entitlements.0.status', 'IN_PROGRESS')
            ->assertJsonPath('entitlements.0.evidence_count', 1);
    }

    public function test_waiving_is_recorded_through_the_same_endpoint(): void
    {
        $sponsorshipId = $this->createContractedSponsorship();

        $entitlementId = $this->postJson(
            "/events/{$this->eventId}/sponsorships/{$sponsorshipId}/entitlements",
            ['entitlement_type' => 'SOCIAL_POST', 'quantity' => 3],
            $this->authHeaders($this->token)
        )->json('id');

        $this->postJson(
            "/events/{$this->eventId}/sponsorship-entitlements/{$entitlementId}/fulfilment",
            ['waived_reason' => 'Sponsor declined social coverage'],
            $this->authHeaders($this->token)
        )->assertOk();

        $this->getJson(
            "/events/{$this->eventId}/sponsorships/{$sponsorshipId}/fulfilment",
            $this->authHeaders($this->token)
        )->assertOk()
            ->assertJsonPath('entitlements.0.status', 'WAIVED')
            ->assertJsonPath('waived_entitlements', 1);
    }

    public function test_over_delivering_is_a_validation_error(): void
    {
        $sponsorshipId = $this->createContractedSponsorship();

        $entitlementId = $this->postJson(
            "/events/{$this->eventId}/sponsorships/{$sponsorshipId}/entitlements",
            ['entitlement_type' => 'GUEST_PASSES', 'quantity' => 2],
            $this->authHeaders($this->token)
        )->json('id');

        $this->postJson(
            "/events/{$this->eventId}/sponsorship-entitlements/{$entitlementId}/fulfilment",
            ['quantity' => 5],
            $this->authHeaders($this->token)
        )->assertStatus(422)->assertJsonValidationErrors('quantity');
    }

    public function test_an_unknown_entitlement_type_is_rejected_by_validation(): void
    {
        $sponsorshipId = $this->createContractedSponsorship();

        $this->postJson(
            "/events/{$this->eventId}/sponsorships/{$sponsorshipId}/entitlements",
            ['entitlement_type' => 'FREE_PONY'],
            $this->authHeaders($this->token)
        )->assertStatus(422)->assertJsonValidationErrors('entitlement_type');
    }

    public function test_a_duplicate_sponsorship_is_a_validation_error(): void
    {
        $companyId = $this->makeCompany();

        $this->postJson(
            "/events/{$this->eventId}/sponsorships",
            ['company_id' => $companyId, 'tier' => 'GOLD'],
            $this->authHeaders($this->token)
        )->assertStatus(201);

        $this->postJson(
            "/events/{$this->eventId}/sponsorships",
            ['company_id' => $companyId, 'tier' => 'SILVER'],
            $this->authHeaders($this->token)
        )->assertStatus(422)->assertJsonValidationErrors('company_id');
    }

    public function test_an_entitlement_of_another_events_sponsorship_cannot_be_fulfilled(): void
    {
        $sponsorshipId = $this->createContractedSponsorship();

        $entitlementId = $this->postJson(
            "/events/{$this->eventId}/sponsorships/{$sponsorshipId}/entitlements",
            ['entitlement_type' => 'GUEST_PASSES', 'quantity' => 5],
            $this->authHeaders($this->token)
        )->json('id');

        // The entitlement id arrives in the URL while authorization was checked on the
        // event, so the two must be confirmed to belong together.
        $response = $this->postJson(
            "/events/{$this->foreignEventId}/sponsorship-entitlements/{$entitlementId}/fulfilment",
            ['quantity' => 1],
            $this->authHeaders($this->token)
        );

        $this->assertContains($response->getStatusCode(), [401, 403, 404, 422]);
        $this->assertSame(
            0,
            (int) DB::table('sponsorship_entitlements')->where('id', $entitlementId)->value('fulfilled_quantity')
        );
    }

    public function test_the_sponsor_endpoints_refuse_a_foreign_event(): void
    {
        $paths = [
            ['GET', "/events/{$this->foreignEventId}/sponsorships"],
            ['POST', "/events/{$this->foreignEventId}/sponsorships"],
            ['POST', "/events/{$this->foreignEventId}/sponsorship-packages"],
            ['PATCH', "/events/{$this->foreignEventId}/sponsorships/1"],
            ['GET', "/events/{$this->foreignEventId}/sponsorships/1/fulfilment"],
        ];

        foreach ($paths as [$method, $path]) {
            $response = $this->json(
                $method,
                $path,
                ['company_id' => 1, 'tier' => 'GOLD', 'name' => 'X'],
                $this->authHeaders($this->token)
            );

            $this->assertContains(
                $response->getStatusCode(),
                [401, 403, 404],
                sprintf('CROSS-TENANT LEAK: %s %s returned %d.', $method, $path, $response->getStatusCode())
            );
        }
    }

    public function test_the_sponsor_endpoints_require_authentication(): void
    {
        $this->getJson("/events/{$this->eventId}/sponsorships")->assertStatus(401);
    }

    public function test_a_role_without_the_sponsor_permission_is_refused(): void
    {
        $operator = User::factory()->create();

        DB::table('account_users')->insert([
            'user_id' => $operator->id,
            'account_id' => $this->accountId,
            'role' => 'CHECKIN_OPERATOR',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $operatorToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser($operator);

        $this->getJson("/events/{$this->eventId}/sponsorships", $this->authHeaders($operatorToken))
            ->assertStatus(403);
    }

    public function test_the_public_strip_needs_no_account(): void
    {
        $this->createContractedSponsorship();

        $this->getJson("/public/events/{$this->eventId}/sponsors")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ---------------------------------------------------------------- fixtures

    private function createContractedSponsorship(?float $contractValue = null): int
    {
        $sponsorshipId = (int) $this->postJson(
            "/events/{$this->eventId}/sponsorships",
            [
                'company_id' => $this->makeCompany('Signed Co'),
                'tier' => 'GOLD',
                'contract_value' => $contractValue,
            ],
            $this->authHeaders($this->token)
        )->assertStatus(201)->json('id');

        $this->patchJson(
            "/events/{$this->eventId}/sponsorships/{$sponsorshipId}",
            ['status' => 'CONTRACTED', 'show_on_event_page' => true],
            $this->authHeaders($this->token)
        )->assertOk();

        return $sponsorshipId;
    }

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
            'name' => 'Sponsor API Organizer',
            'email' => 'spapi-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Sponsor API Event',
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'start_date' => now()->addDays(15),
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
