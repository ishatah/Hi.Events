<?php

namespace Tests\Feature\Services\Infrastructure\Tenancy;

use HiEvents\Models\Event;
use HiEvents\Models\Organizer;
use HiEvents\Models\User;
use HiEvents\Models\Venue;
use HiEvents\Services\Infrastructure\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use DatabaseTransactions;

    private TenantContext $context;

    private int $accountA;

    private int $accountB;

    private int $eventA;

    private int $eventB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(TenantContext::class);
        $this->context->clear();

        [$this->accountA, $userA] = $this->makeAccount();
        [$this->accountB, $userB] = $this->makeAccount();

        $this->eventA = $this->makeEvent($this->accountA, $userA);
        $this->eventB = $this->makeEvent($this->accountB, $userB);
    }

    protected function tearDown(): void
    {
        $this->context->clear();

        parent::tearDown();
    }

    public function test_a_scoped_query_returns_only_the_current_tenants_rows(): void
    {
        $this->context->setAccountId($this->accountA);

        $ids = Event::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $this->assertContains($this->eventA, $ids);
        $this->assertNotContains($this->eventB, $ids);
    }

    public function test_finding_another_tenants_row_by_id_returns_nothing(): void
    {
        $this->context->setAccountId($this->accountA);

        $this->assertNull(
            Event::query()->find($this->eventB),
            'A direct id lookup must not bypass the tenant scope. This is the case a '
            .'forgotten authorization check relies on.'
        );
    }

    public function test_no_tenant_set_leaves_queries_unscoped(): void
    {
        $ids = Event::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $this->assertContains($this->eventA, $ids);
        $this->assertContains(
            $this->eventB,
            $ids,
            'Console commands, queue workers and the public storefront run with no tenant '
            .'and must not silently see nothing.'
        );
    }

    public function test_the_escape_hatch_suspends_scoping_only_inside_the_callback(): void
    {
        $this->context->setAccountId($this->accountA);

        $inside = $this->context->withoutScope(
            fn (): array => Event::query()->pluck('id')->map(fn ($id): int => (int) $id)->all()
        );

        $this->assertContains($this->eventB, $inside);

        $after = Event::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $this->assertNotContains(
            $this->eventB,
            $after,
            'The escape hatch must not leave scoping disabled after it returns.'
        );
    }

    public function test_the_escape_hatch_restores_scoping_when_the_callback_throws(): void
    {
        $this->context->setAccountId($this->accountA);

        try {
            $this->context->withoutScope(static function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($this->context->isScopeEnabled());
        $this->assertNotContains(
            $this->eventB,
            Event::query()->pluck('id')->map(fn ($id): int => (int) $id)->all()
        );
    }

    public function test_the_scope_applies_to_every_tenant_owned_model(): void
    {
        $this->context->setAccountId($this->accountA);

        foreach ([Event::class, Organizer::class, Venue::class] as $model) {
            $sql = $model::query()->toSql();

            $this->assertStringContainsString(
                'account_id',
                $sql,
                sprintf('%s must carry the tenant scope.', $model)
            );
        }
    }

    public function test_the_tenant_context_is_not_shared_between_units_of_work(): void
    {
        $this->context->setAccountId($this->accountA);

        // A scoped binding is rebuilt per request and per queued job. Forgetting the
        // instance is how the container simulates the next unit of work starting, and is
        // what the previous static property on User could never do.
        app()->forgetScopedInstances();

        $this->assertFalse(
            app(TenantContext::class)->hasAccount(),
            'A new unit of work must not inherit the previous tenant.'
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function makeAccount(): array
    {
        $user = User::factory()->withAccount()->create();

        return [(int) $user->accounts()->first()->id, (int) $user->id];
    }

    private function makeEvent(int $accountId, int $userId): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Tenant Scope Organizer',
            'email' => 'ts-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Tenant Scope Event',
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'start_date' => now()->addDays(10),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'DRAFT',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
