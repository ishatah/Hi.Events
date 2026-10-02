<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Throttling;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OrderCreateThrottleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_order_creation_is_throttled(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route): bool => $route->uri() === 'public/events/{event_id}/order'
                && in_array('POST', $route->methods(), true));

        $this->assertNotNull($route, 'Expected a public order creation route.');

        $this->assertContains(
            'throttle:order-create',
            $route->gatherMiddleware(),
            'Order creation applies a promo code, so under only the global API limit a hidden '
            .'VIP or comp product is brute-forceable far faster than the dedicated promo '
            .'validation throttle allows.'
        );
    }

    public function test_the_order_creation_limit_is_below_the_global_api_limit(): void
    {
        $this->assertLessThan(
            (int) config('app.api_rate_limit_per_minute'),
            (int) config('app.order_create_rate_limit_per_minute'),
            'A dedicated limit no tighter than the global one closes nothing.'
        );
    }

    public function test_the_limit_leaves_room_for_several_buyers_behind_one_address(): void
    {
        $this->assertGreaterThanOrEqual(
            20,
            (int) config('app.order_create_rate_limit_per_minute'),
            'A checkout makes one request and the key is IP plus event, so a venue or office '
            .'behind a single address must not be cut off.'
        );
    }
}
