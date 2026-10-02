<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Exceptions\EventStatusTransitionException;
use HiEvents\Models\User;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventStatusDTO;
use HiEvents\Services\Application\Handlers\Event\UpdateEventStatusHandler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdateEventStatusGuardsTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateEventStatusHandler $handler;

    private int $accountId;

    private int $userId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = app(UpdateEventStatusHandler::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;

        DB::table('accounts')->where('id', $this->accountId)->update([
            'account_verified_at' => now(),
        ]);

        $this->organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Guard Organizer',
            'email' => 'organizer-'.Str::lower(Str::random(8)).'@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_an_archived_event_cannot_be_put_back_on_sale(): void
    {
        $eventId = $this->makeEvent(EventStatus::ARCHIVED);
        $this->addProduct($eventId);

        $this->expectException(EventStatusTransitionException::class);

        $this->updateStatus($eventId, EventStatus::LIVE);
    }

    public function test_an_organizer_cannot_move_an_event_into_manual_review(): void
    {
        $eventId = $this->makeEvent(EventStatus::DRAFT);

        $this->expectException(EventStatusTransitionException::class);

        $this->updateStatus($eventId, EventStatus::PENDING_MANUAL_REVIEW);
    }

    public function test_a_draft_event_goes_on_sale(): void
    {
        $eventId = $this->makeEvent(EventStatus::DRAFT);
        $this->addProduct($eventId);

        $this->updateStatus($eventId, EventStatus::LIVE);

        $this->assertSame(
            EventStatus::LIVE->name,
            DB::table('events')->where('id', $eventId)->value('status')
        );
    }

    public function test_a_live_event_can_be_withdrawn_to_draft(): void
    {
        $eventId = $this->makeEvent(EventStatus::LIVE);
        $this->addProduct($eventId);

        $this->updateStatus($eventId, EventStatus::DRAFT);

        $this->assertSame(
            EventStatus::DRAFT->name,
            DB::table('events')->where('id', $eventId)->value('status')
        );
    }

    private function updateStatus(int $eventId, EventStatus $status): void
    {
        $this->handler->handle(UpdateEventStatusDTO::fromArray([
            'status' => $status->name,
            'eventId' => $eventId,
            'accountId' => $this->accountId,
        ]));
    }

    private function makeEvent(EventStatus $status): int
    {
        return DB::table('events')->insertGetId([
            'short_id' => 'ev'.Str::lower(Str::random(10)),
            'title' => 'Guard Event',
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'status' => $status->name,
            'start_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addProduct(int $eventId): void
    {
        DB::table('products')->insert([
            'title' => 'Guard Ticket',
            'event_id' => $eventId,
            'type' => 'FREE',
            'product_type' => 'TICKET',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
