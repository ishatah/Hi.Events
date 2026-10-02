<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\Account;

use HiEvents\DomainObjects\Enums\AccountDeletionInitiator;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Account\AccountDeletionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountDeletionEventRestoreTest extends TestCase
{
    use DatabaseTransactions;

    private AccountDeletionService $service;

    private int $accountId;

    private int $userId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->service = app(AccountDeletionService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;

        $this->organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Restore Organizer',
            'email' => 'organizer-'.Str::lower(Str::random(8)).'@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cancelling_a_deletion_request_puts_the_events_back_on_sale(): void
    {
        $liveEventId = $this->makeEvent(EventStatus::LIVE);
        $reviewEventId = $this->makeEvent(EventStatus::PENDING_MANUAL_REVIEW);
        $alreadyDraftId = $this->makeEvent(EventStatus::DRAFT);

        $this->service->requestDeletion(
            accountId: $this->accountId,
            requestedByUserId: $this->userId,
            initiator: AccountDeletionInitiator::ACCOUNT_OWNER,
        );

        $this->assertSame(EventStatus::DRAFT->name, $this->statusOf($liveEventId));
        $this->assertSame(EventStatus::DRAFT->name, $this->statusOf($reviewEventId));

        $this->service->cancelDeletion($this->accountId, $this->userId);

        $this->assertSame(
            EventStatus::LIVE->name,
            $this->statusOf($liveEventId),
            'Requesting deletion takes live events off sale; cancelling it must put them '
            .'back, or an organizer who changes their mind silently loses every event.'
        );

        $this->assertSame(
            EventStatus::PENDING_MANUAL_REVIEW->name,
            $this->statusOf($reviewEventId),
            'An event under review must return to review, not go straight on sale.'
        );

        $this->assertSame(
            EventStatus::DRAFT->name,
            $this->statusOf($alreadyDraftId),
            'An event that was already a draft is not promoted by a cancellation.'
        );
    }

    public function test_the_suspended_statuses_are_recorded_on_the_request(): void
    {
        $liveEventId = $this->makeEvent(EventStatus::LIVE);

        $request = $this->service->requestDeletion(
            accountId: $this->accountId,
            requestedByUserId: $this->userId,
            initiator: AccountDeletionInitiator::ACCOUNT_OWNER,
        );

        $recorded = DB::table('account_deletion_requests')
            ->where('id', $request->getId())
            ->value('suspended_event_statuses');

        $this->assertSame(
            EventStatus::LIVE->name,
            json_decode((string) $recorded, true)[$liveEventId] ?? null
        );
    }

    private function statusOf(int $eventId): string
    {
        return (string) DB::table('events')->where('id', $eventId)->value('status');
    }

    private function makeEvent(EventStatus $status): int
    {
        return DB::table('events')->insertGetId([
            'short_id' => 'ev'.Str::lower(Str::random(10)),
            'title' => 'Restore Event',
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
}
