<?php

namespace Tests\Feature\Http\Actions\Admin;

use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\AuthenticatesApiRequests;
use Tests\TestCase;

class MessageReviewTest extends TestCase
{
    use AuthenticatesApiRequests;
    use DatabaseTransactions;

    private User $user;

    private string $token;

    private int $accountId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->user, $this->token, $this->accountId] = $this->makeAuthenticatedUser('SUPERADMIN');
        $this->eventId = $this->makeEvent();
    }

    public function test_a_flagged_message_can_be_rejected(): void
    {
        $messageId = $this->makeMessage(MessageStatus::PENDING_REVIEW->name);

        $this->postJson(
            "/admin/messages/{$messageId}/reject",
            ['reason' => 'Reads as phishing'],
            $this->authHeaders($this->token)
        )->assertOk();

        $message = DB::table('messages')->where('id', $messageId)->first();

        $this->assertSame(
            MessageStatus::CANCELLED->name,
            $message->status,
            'Without a reject route a message a reviewer judged to be spam sat in pending '
            .'review forever, with no way to close it out.'
        );
        $this->assertSame('Reads as phishing', $message->rejection_reason);
    }

    public function test_a_rejection_reason_is_optional(): void
    {
        $messageId = $this->makeMessage(MessageStatus::PENDING_REVIEW->name);

        $this->postJson("/admin/messages/{$messageId}/reject", [], $this->authHeaders($this->token))
            ->assertOk();

        $this->assertNull(DB::table('messages')->where('id', $messageId)->value('rejection_reason'));
    }

    public function test_a_message_that_is_not_under_review_cannot_be_rejected(): void
    {
        $messageId = $this->makeMessage(MessageStatus::SENT->name);

        $this->postJson("/admin/messages/{$messageId}/reject", [], $this->authHeaders($this->token))
            ->assertStatus(422);

        $this->assertSame(
            MessageStatus::SENT->name,
            DB::table('messages')->where('id', $messageId)->value('status'),
            'A message already sent cannot be un-sent by rejecting it.'
        );
    }

    public function test_rejecting_twice_is_refused(): void
    {
        $messageId = $this->makeMessage(MessageStatus::PENDING_REVIEW->name);

        $this->postJson("/admin/messages/{$messageId}/reject", ['reason' => 'First'], $this->authHeaders($this->token))
            ->assertOk();

        $this->postJson("/admin/messages/{$messageId}/reject", ['reason' => 'Second'], $this->authHeaders($this->token))
            ->assertStatus(422);

        $this->assertSame(
            'First',
            DB::table('messages')->where('id', $messageId)->value('rejection_reason'),
            'The first reason is the decision; a second would overwrite the record of it.'
        );
    }

    public function test_an_unknown_message_cannot_be_rejected(): void
    {
        $this->postJson('/admin/messages/99999999/reject', [], $this->authHeaders($this->token))
            ->assertStatus(404);
    }

    public function test_only_a_superadmin_can_reject(): void
    {
        [, $adminToken] = $this->makeAuthenticatedUser('ADMIN');
        $messageId = $this->makeMessage(MessageStatus::PENDING_REVIEW->name);

        $this->postJson("/admin/messages/{$messageId}/reject", [], $this->authHeaders($adminToken))
            ->assertStatus(403);

        $this->assertSame(
            MessageStatus::PENDING_REVIEW->name,
            DB::table('messages')->where('id', $messageId)->value('status')
        );
    }

    public function test_rejecting_requires_authentication(): void
    {
        $messageId = $this->makeMessage(MessageStatus::PENDING_REVIEW->name);

        $this->postJson("/admin/messages/{$messageId}/reject")->assertStatus(401);
    }

    // ---------------------------------------------------------------- fixtures

    private function makeMessage(string $status): int
    {
        return (int) DB::table('messages')->insertGetId([
            'event_id' => $this->eventId,
            'subject' => 'A message under review',
            'message' => '<p>Body</p>',
            'type' => 'ALL_ATTENDEES',
            'status' => $status,
            'sent_by_user_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: User, 1: string, 2: int}
     */
    private function makeAuthenticatedUser(string $role): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = (int) $user->accounts()->first()->id;

        DB::table('account_users')
            ->where('account_id', $accountId)
            ->where('user_id', $user->id)
            ->update(['role' => $role]);

        $token = JWTAuth::claims(['account_id' => $accountId])->fromUser($user);

        return [$user, $token, $accountId];
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Message Review Organizer',
            'email' => 'review-'.uniqid().'@test.local',
            'currency' => 'QAR',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Message Review Event',
            'account_id' => $this->accountId,
            'user_id' => $this->user->id,
            'organizer_id' => $organizerId,
            'status' => 'LIVE',
            'currency' => 'QAR',
            'timezone' => 'UTC',
            'short_id' => 'ev_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
