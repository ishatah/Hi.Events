<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Actions\Attendees;

use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicAttendeeTicketIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    private int $eventId;

    private int $otherEventId;

    private string $attendeeShortId;

    private string $attendeePublicId;

    private string $orderShortId;

    private int $attendeeId;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = Str::lower(Str::random(10));
        $now = now()->toDateTimeString();

        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Integrity Organizer',
            'email' => 'organizer-'.$suffix.'@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->eventId = $this->createEvent($accountId, $user->id, $organizerId, 'ev'.$suffix, $now);
        $this->otherEventId = $this->createEvent($accountId, $user->id, $organizerId, 'ev2'.$suffix, $now);

        DB::table('event_settings')->insert([
            'event_id' => $this->eventId,
            'allow_attendee_self_edit' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $productId = DB::table('products')->insertGetId([
            'title' => 'Integrity Ticket',
            'event_id' => $this->eventId,
            'type' => 'FREE',
            'product_type' => 'TICKET',
            'order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $priceId = DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->orderShortId = 'os'.$suffix;

        $orderId = DB::table('orders')->insertGetId([
            'short_id' => $this->orderShortId,
            'public_id' => 'OP'.Str::upper($suffix),
            'event_id' => $this->eventId,
            'status' => 'COMPLETED',
            'email' => 'buyer-'.$suffix.'@example.test',
            'first_name' => 'Integrity',
            'last_name' => 'Buyer',
            'currency' => 'USD',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->attendeeShortId = 'at'.$suffix;
        $this->attendeePublicId = 'AP'.Str::upper($suffix);

        $this->attendeeId = DB::table('attendees')->insertGetId([
            'short_id' => $this->attendeeShortId,
            'public_id' => $this->attendeePublicId,
            'first_name' => 'Integrity',
            'last_name' => 'Attendee',
            'email' => 'attendee-'.$suffix.'@example.test',
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $priceId,
            'event_id' => $this->eventId,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_the_attendee_is_readable_through_its_own_event(): void
    {
        $this->getJson(sprintf('/public/events/%d/attendees/%s', $this->eventId, $this->attendeeShortId))
            ->assertSuccessful();
    }

    public function test_the_attendee_is_not_readable_through_another_event(): void
    {
        $this->getJson(sprintf('/public/events/%d/attendees/%s', $this->otherEventId, $this->attendeeShortId))
            ->assertStatus(404);
    }

    public function test_changing_the_email_rotates_the_ticket_qr_identifier(): void
    {
        $this->patchJson(
            sprintf(
                '/public/events/%d/order/%s/attendees/%s',
                $this->eventId,
                $this->orderShortId,
                $this->attendeeShortId
            ),
            ['email' => 'moved-'.Str::lower(Str::random(8)).'@example.test']
        )->assertSuccessful();

        $this->assertDatabaseMissing('attendees', [
            'id' => $this->attendeeId,
            'public_id' => $this->attendeePublicId,
        ]);

        $this->assertNotSame(
            $this->attendeePublicId,
            DB::table('attendees')->where('id', $this->attendeeId)->value('public_id'),
            'public_id is the value encoded in the ticket QR, so leaving it unchanged means a '
            .'QR already printed or screenshotted by the previous holder still scans and '
            .'checks in after the ticket has been transferred to a new email.'
        );
    }

    private function createEvent(int $accountId, int $userId, int $organizerId, string $shortId, string $now): int
    {
        return DB::table('events')->insertGetId([
            'short_id' => $shortId,
            'title' => 'Integrity Event',
            'account_id' => $accountId,
            'user_id' => $userId,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'start_date' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
