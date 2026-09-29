<?php

namespace Tests\Feature\Services\Application\Handlers\Attendee;

use HiEvents\DomainObjects\Enums\CheckInAction;
use HiEvents\Models\User;
use HiEvents\Services\Application\Handlers\Attendee\CheckInAttendeeHandler;
use HiEvents\Services\Application\Handlers\Attendee\DTO\CheckInAttendeeDTO;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\CheckinEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Finding F10: the dashboard and the public scanner were two independent write models.
 * These assert they now agree.
 *
 * @see docs/arzo-master-plan/02-current-state-audit.md F10
 */
class CheckInConsolidationTest extends TestCase
{
    use DatabaseTransactions;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $checkInListId;

    private string $attendeePublicId;

    private int $attendeeId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;

        $this->eventId = $this->makeEvent();
        $this->checkInListId = $this->makeSystemDefaultCheckInList();
        [$this->attendeeId, $this->attendeePublicId] = $this->makeAttendee();
    }

    public function test_a_dashboard_check_in_writes_an_attendee_check_in_row(): void
    {
        $this->handler()->handle($this->dto(CheckInAction::CHECK_IN));

        $this->assertSame(
            1,
            DB::table('attendee_check_ins')->where('attendee_id', $this->attendeeId)->count(),
            'A dashboard check-in must produce the same row the scanner does.'
        );
    }

    public function test_a_dashboard_check_in_still_sets_the_attendee_columns(): void
    {
        $this->handler()->handle($this->dto(CheckInAction::CHECK_IN));

        $attendee = DB::table('attendees')->where('id', $this->attendeeId)->first();

        $this->assertNotNull(
            $attendee->checked_in_at,
            'The attendee list and exports read these columns; they must stay in step.'
        );
        $this->assertSame($this->userId, (int) $attendee->checked_in_by);
    }

    public function test_a_dashboard_check_in_dispatches_the_domain_event(): void
    {
        $dispatched = [];

        $dispatcher = Mockery::mock(DomainEventDispatcherService::class);
        $dispatcher->shouldReceive('dispatch')
            ->andReturnUsing(function (CheckinEvent $event) use (&$dispatched) {
                $dispatched[] = $event->type;
            });

        $this->app->instance(DomainEventDispatcherService::class, $dispatcher);

        $this->handler()->handle($this->dto(CheckInAction::CHECK_IN));

        $this->assertContains(
            DomainEventType::CHECKIN_CREATED,
            $dispatched,
            'A dashboard check-in was previously invisible to webhooks.'
        );
    }

    public function test_checking_out_removes_the_row_and_clears_the_columns(): void
    {
        $handler = $this->handler();

        $handler->handle($this->dto(CheckInAction::CHECK_IN));
        $handler->handle($this->dto(CheckInAction::CHECK_OUT));

        // Soft-deleted rather than erased: the check-in history is evidence of who was
        // where, so a check-out marks the row rather than removing it.
        $this->assertSame(
            0,
            DB::table('attendee_check_ins')
                ->where('attendee_id', $this->attendeeId)
                ->whereNull('deleted_at')
                ->count()
        );
        $this->assertSame(
            1,
            DB::table('attendee_check_ins')
                ->where('attendee_id', $this->attendeeId)
                ->whereNotNull('deleted_at')
                ->count()
        );
        $this->assertNull(
            DB::table('attendees')->where('id', $this->attendeeId)->value('checked_in_at')
        );
    }

    public function test_checking_out_dispatches_the_deleted_event(): void
    {
        $this->handler()->handle($this->dto(CheckInAction::CHECK_IN));

        $dispatched = [];

        $dispatcher = Mockery::mock(DomainEventDispatcherService::class);
        $dispatcher->shouldReceive('dispatch')
            ->andReturnUsing(function (CheckinEvent $event) use (&$dispatched) {
                $dispatched[] = $event->type;
            });

        $this->app->instance(DomainEventDispatcherService::class, $dispatcher);

        $this->handler()->handle($this->dto(CheckInAction::CHECK_OUT));

        $this->assertContains(DomainEventType::CHECKIN_DELETED, $dispatched);
    }

    public function test_checking_in_twice_is_refused(): void
    {
        $handler = $this->handler();
        $handler->handle($this->dto(CheckInAction::CHECK_IN));

        $this->expectExceptionMessageMatches('/already checked in/i');
        $handler->handle($this->dto(CheckInAction::CHECK_IN));
    }

    public function test_checking_out_somebody_who_is_not_in_is_refused(): void
    {
        $this->expectExceptionMessageMatches('/already checked out/i');
        $this->handler()->handle($this->dto(CheckInAction::CHECK_OUT));
    }

    public function test_a_cancelled_attendee_cannot_be_checked_in(): void
    {
        DB::table('attendees')->where('id', $this->attendeeId)->update(['status' => 'CANCELLED']);

        $this->expectExceptionMessageMatches('/not active/i');
        $this->handler()->handle($this->dto(CheckInAction::CHECK_IN));
    }

    public function test_an_event_without_a_default_list_reports_it_rather_than_failing_silently(): void
    {
        DB::table('check_in_lists')->where('id', $this->checkInListId)->update(['is_system_default' => false]);

        $this->expectExceptionMessage('This event has no default check-in list.');
        $this->handler()->handle($this->dto(CheckInAction::CHECK_IN));
    }

    private function handler(): CheckInAttendeeHandler
    {
        return app(CheckInAttendeeHandler::class);
    }

    private function dto(string $action): CheckInAttendeeDTO
    {
        return new CheckInAttendeeDTO(
            attendee_public_id: $this->attendeePublicId,
            event_id: $this->eventId,
            action: $action,
            checked_in_by_user_id: $this->userId,
        );
    }

    private function makeSystemDefaultCheckInList(): int
    {
        return (int) DB::table('check_in_lists')->insertGetId([
            'short_id' => 'cl_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'name' => 'Default',
            'is_system_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function makeAttendee(): array
    {
        $productId = (int) DB::table('products')->insertGetId([
            'title' => 'Consolidation Ticket',
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
            'first_name' => 'Check',
            'last_name' => 'In',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $publicId = 'A-'.Str::upper(Str::random(10));

        $attendeeId = (int) DB::table('attendees')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(16)),
            'public_id' => $publicId,
            'first_name' => 'Check',
            'last_name' => 'Subject',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $productPriceId,
            'event_id' => $this->eventId,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$attendeeId, $publicId];
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Consolidation Organizer',
            'email' => 'cons-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventId = (int) DB::table('events')->insertGetId([
            'title' => 'Consolidation Event',
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

        DB::table('event_settings')->insert([
            'event_id' => $eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $eventId;
    }
}
