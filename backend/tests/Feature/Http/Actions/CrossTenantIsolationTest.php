<?php

namespace Tests\Feature\Http\Actions;

use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AuthenticatesApiRequests;
use Tests\TestCase;

/**
 * Tenant isolation currently rests on every action remembering to call
 * isActionAuthorized(). There are no Eloquent global scopes, and child resources are
 * scoped by their parent id rather than by account, so a single omission is a
 * cross-tenant read with no second line of defence.
 *
 * These tests assert the boundary from the outside: tenant A authenticates and is
 * refused on every one of tenant B's resources. They must keep passing through the
 * RBAC migration, which rewrites all 159 authorization call sites.
 *
 * @see docs/arzo-master-plan/02-current-state-audit.md finding F11
 * @see docs/arzo-master-plan/08-multi-tenancy.md
 */
class CrossTenantIsolationTest extends TestCase
{
    use AuthenticatesApiRequests;
    use DatabaseTransactions;

    private string $tenantAToken;

    private int $tenantBEventId;

    private int $tenantBOrganizerId;

    private int $tenantBProductId;

    private int $tenantBAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->tenantAToken] = $this->makeTenant('A');

        [$userB, , $this->tenantBAccountId] = $this->makeTenant('B');
        $this->tenantBOrganizerId = $this->makeOrganizer($this->tenantBAccountId);
        $this->tenantBEventId = $this->makeEvent($this->tenantBAccountId, $userB->id, $this->tenantBOrganizerId);
        $this->tenantBProductId = $this->makeProduct($this->tenantBEventId);
    }

    /**
     * @return array<int, array{0: string, 1: string, 2?: array<string, mixed>}> [method, uri, payload]
     */
    public static function foreignResourceProvider(): array
    {
        return [
            'get event' => ['GET', '/events/{event}'],
            // A valid body, so FormRequest validation cannot short-circuit to 422
            // before authorization runs.
            'update event' => ['PUT', '/events/{event}', [
                'title' => 'Renamed by another tenant',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'start_date' => '2030-01-01T10:00:00',
            ]],
            'get event settings' => ['GET', '/events/{event}/settings'],
            'list products' => ['GET', '/events/{event}/products'],
            'list attendees' => ['GET', '/events/{event}/attendees'],
            'list orders' => ['GET', '/events/{event}/orders'],
            'list questions' => ['GET', '/events/{event}/questions'],
            'list promo codes' => ['GET', '/events/{event}/promo-codes'],
            'list check-in lists' => ['GET', '/events/{event}/check-in-lists'],
            'list capacity assignments' => ['GET', '/events/{event}/capacity-assignments'],
            'list webhooks' => ['GET', '/events/{event}/webhooks'],
            'list affiliates' => ['GET', '/events/{event}/affiliates'],
            'list messages' => ['GET', '/events/{event}/messages'],
            'event stats' => ['GET', '/events/{event}/stats'],
            'get organizer' => ['GET', '/organizers/{organizer}'],
            'get product' => ['GET', '/events/{event}/products/{product}'],
            'delete product' => ['DELETE', '/events/{event}/products/{product}'],
        ];
    }

    #[DataProvider('foreignResourceProvider')]
    public function test_tenant_cannot_reach_another_tenants_resource(
        string $method,
        string $uri,
        array $payload = []
    ): void {
        $resolved = str_replace(
            ['{event}', '{organizer}', '{product}'],
            [(string) $this->tenantBEventId, (string) $this->tenantBOrganizerId, (string) $this->tenantBProductId],
            $uri
        );

        $response = $this->json($method, $resolved, $payload, $this->authHeaders($this->tenantAToken));

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403, 404],
            sprintf(
                'CROSS-TENANT LEAK: %s %s returned %d for a foreign resource. Expected 401, 403 or 404.',
                $method,
                $resolved,
                $response->getStatusCode()
            )
        );
    }

    public function test_event_listing_excludes_other_tenants_events(): void
    {
        $response = $this->getJson('/events', $this->authHeaders($this->tenantAToken));

        $response->assertOk();

        $ids = array_column((array) $response->json('data'), 'id');

        $this->assertNotContains(
            $this->tenantBEventId,
            $ids,
            'CROSS-TENANT LEAK: tenant A can see tenant B event '.$this->tenantBEventId.' in /events.'
        );
    }

    public function test_organizer_listing_excludes_other_tenants_organizers(): void
    {
        $response = $this->getJson('/organizers', $this->authHeaders($this->tenantAToken));

        $response->assertOk();

        $ids = array_column((array) $response->json('data'), 'id');

        $this->assertNotContains(
            $this->tenantBOrganizerId,
            $ids,
            'CROSS-TENANT LEAK: tenant A can see tenant B organizer in /organizers.'
        );
    }

    public function test_tenant_cannot_create_a_product_on_another_tenants_event(): void
    {
        $response = $this->postJson(
            "/events/{$this->tenantBEventId}/products",
            [
                'title' => 'Injected by another tenant',
                'type' => 'FREE',
                'product_type' => 'TICKET',
            ],
            $this->authHeaders($this->tenantAToken)
        );

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403, 404, 422],
            'CROSS-TENANT WRITE: tenant A created a product on tenant B event.'
        );

        $this->assertDatabaseMissing('products', [
            'event_id' => $this->tenantBEventId,
            'title' => 'Injected by another tenant',
        ]);
    }

    /**
     * @return array{0: User, 1: string, 2: int}
     */
    private function makeTenant(string $label): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $token = JWTAuth::claims(['account_id' => $accountId])->fromUser($user);

        return [$user, $token, $accountId];
    }

    private function makeOrganizer(int $accountId): int
    {
        return DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Tenant Organizer '.uniqid(),
            'email' => 'organizer-'.uniqid().'@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(int $accountId, int $userId, int $organizerId): int
    {
        return DB::table('events')->insertGetId([
            'title' => 'Tenant Event '.uniqid(),
            'account_id' => $accountId,
            'user_id' => $userId,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'ev_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeProduct(int $eventId): int
    {
        return DB::table('products')->insertGetId([
            'title' => 'Tenant Product '.uniqid(),
            'event_id' => $eventId,
            'type' => 'FREE',
            'product_type' => 'TICKET',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
}
