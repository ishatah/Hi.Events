<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\OrderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderRepositoryAdminSearchTest extends TestCase
{
    use DatabaseTransactions;

    private OrderRepository $repository;

    private string $orderEmail;

    private string $orderShortId;

    private string $orderPublicId;

    private int $orderId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(OrderRepository::class);

        $suffix = Str::lower(Str::random(10));
        $this->orderEmail = 'buyer-'.$suffix.'@example.test';
        $this->orderShortId = 'os'.$suffix;
        $this->orderPublicId = 'OP'.Str::upper($suffix);

        $now = now()->toDateTimeString();

        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Search Organizer',
            'email' => 'organizer-'.$suffix.'@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $eventId = DB::table('events')->insertGetId([
            'short_id' => 'ev'.$suffix,
            'title' => 'Search Event',
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'start_date' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->orderId = DB::table('orders')->insertGetId([
            'short_id' => $this->orderShortId,
            'public_id' => $this->orderPublicId,
            'event_id' => $eventId,
            'status' => OrderStatus::COMPLETED->name,
            'email' => $this->orderEmail,
            'first_name' => 'Searchable',
            'last_name' => 'Buyer'.$suffix,
            'currency' => 'USD',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function returnedOrderIds(LengthAwarePaginator $results): array
    {
        return array_map(
            static fn (OrderDomainObject $order): int => $order->getId(),
            $results->items()
        );
    }

    public function test_searching_by_email_does_not_fail_on_an_ambiguous_column(): void
    {
        $results = $this->repository->getAllOrdersForAdmin(search: $this->orderEmail);

        $this->assertContains(
            $this->orderId,
            $this->returnedOrderIds($results),
            'The query joins accounts, which also has an email column, so an unqualified '
            .'reference makes Postgres reject every search term outright.'
        );
    }

    public function test_searching_by_short_id_does_not_fail_on_an_ambiguous_column(): void
    {
        $results = $this->repository->getAllOrdersForAdmin(search: $this->orderShortId);

        $this->assertContains($this->orderId, $this->returnedOrderIds($results));
    }

    public function test_searching_by_public_id_first_name_and_last_name_each_match(): void
    {
        foreach ([$this->orderPublicId, 'Searchable', 'Buyer'] as $term) {
            $this->assertContains(
                $this->orderId,
                $this->returnedOrderIds($this->repository->getAllOrdersForAdmin(search: $term)),
                sprintf('Searching for "%s" did not return the seeded order.', $term)
            );
        }
    }

    public function test_a_search_matching_only_the_account_email_does_not_return_the_order(): void
    {
        $accountEmail = DB::table('accounts')
            ->join('events', 'events.account_id', '=', 'accounts.id')
            ->where('events.id', DB::table('orders')->where('id', $this->orderId)->value('event_id'))
            ->value('accounts.email');

        if (! $accountEmail || str_contains($this->orderEmail, (string) $accountEmail)) {
            $this->markTestSkipped('The seeded account has no email distinct from the order email.');
        }

        $results = $this->repository->getAllOrdersForAdmin(search: (string) $accountEmail);

        $this->assertNotContains(
            $this->orderId,
            $this->returnedOrderIds($results),
            'The search is over the buyer, so qualifying it to accounts would silently '
            .'change which orders an admin sees.'
        );
    }

    public function test_an_unmatched_term_returns_no_rows_rather_than_erroring(): void
    {
        $results = $this->repository->getAllOrdersForAdmin(search: 'no-such-order-'.Str::random(12));

        $this->assertNotContains($this->orderId, $this->returnedOrderIds($results));
    }
}
