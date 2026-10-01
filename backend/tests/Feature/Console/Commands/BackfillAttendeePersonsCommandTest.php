<?php

namespace Tests\Feature\Console\Commands;

use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackfillAttendeePersonsCommandTest extends TestCase
{
    use DatabaseTransactions;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $productId;

    private int $productPriceId;

    private int $orderId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent();
        [$this->productId, $this->productPriceId] = $this->makeProduct();
        $this->orderId = $this->makeOrder();
    }

    public function test_every_attendee_is_linked_even_across_many_batches(): void
    {
        // More than one batch, because the defect only shows once pagination advances: the
        // original migration filtered on the column it was setting, so processed rows left
        // the result set and the offset stepped past the ones that shifted down.
        $attendeeIds = $this->makeAttendees(12);

        $this->artisan('backfill:attendee-persons', ['--chunk' => 3])
            ->assertExitCode(0);

        $unlinked = DB::table('attendees')
            ->whereIn('id', $attendeeIds)
            ->whereNull('person_id')
            ->count();

        $this->assertSame(
            0,
            $unlinked,
            'Attendees left without a person record have nothing for accreditation, '
            .'credentials or access logs to attach to.'
        );
    }

    public function test_attendees_sharing_an_email_share_one_person(): void
    {
        $this->makeAttendees(1, email: 'layla@example.test');
        $this->makeAttendees(1, email: 'LAYLA@example.test');

        $this->artisan('backfill:attendee-persons')->assertExitCode(0);

        $this->assertSame(
            1,
            DB::table('persons')
                ->where('account_id', $this->accountId)
                ->whereRaw('lower(email) = ?', ['layla@example.test'])
                ->count(),
            'One person per account and lowercased email, so the same human is not duplicated.'
        );
    }

    public function test_attendees_with_a_blank_email_each_get_their_own_person(): void
    {
        $this->makeAttendees(1, email: '');
        $this->makeAttendees(1, email: '');

        $this->artisan('backfill:attendee-persons')->assertExitCode(0);

        $this->assertSame(
            2,
            DB::table('persons')->where('account_id', $this->accountId)->count(),
            'Guessing that two people with no usable email are the same person is a privacy '
            .'hazard, so each gets their own record.'
        );
    }

    public function test_running_twice_changes_nothing_the_second_time(): void
    {
        $this->makeAttendees(5);

        $this->artisan('backfill:attendee-persons')->assertExitCode(0);
        $personsAfterFirst = DB::table('persons')->where('account_id', $this->accountId)->count();

        $this->artisan('backfill:attendee-persons')->assertExitCode(0);

        $this->assertSame(
            $personsAfterFirst,
            DB::table('persons')->where('account_id', $this->accountId)->count(),
            'Repairing an installation must be safe to repeat; a second run that duplicated '
            .'people would be worse than the gap it fixed.'
        );
    }

    public function test_an_already_linked_attendee_is_left_alone(): void
    {
        $attendeeIds = $this->makeAttendees(1);
        $existingPersonId = $this->makePerson('kept@example.test');

        DB::table('attendees')->where('id', $attendeeIds[0])->update(['person_id' => $existingPersonId]);

        $this->artisan('backfill:attendee-persons')->assertExitCode(0);

        $this->assertSame(
            $existingPersonId,
            (int) DB::table('attendees')->where('id', $attendeeIds[0])->value('person_id')
        );
    }

    public function test_a_soft_deleted_attendee_is_not_linked(): void
    {
        $attendeeIds = $this->makeAttendees(1);
        DB::table('attendees')->where('id', $attendeeIds[0])->update(['deleted_at' => now()]);

        $this->artisan('backfill:attendee-persons')->assertExitCode(0);

        $this->assertNull(
            DB::table('attendees')->where('id', $attendeeIds[0])->value('person_id'),
            'A deleted attendee is not somebody who needs an identity record.'
        );
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->makeAttendees(3);

        $this->artisan('backfill:attendee-persons', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(
            3,
            DB::table('attendees')
                ->where('event_id', $this->eventId)
                ->whereNull('person_id')
                ->count()
        );
        $this->assertSame(0, DB::table('persons')->where('account_id', $this->accountId)->count());
    }

    public function test_it_reports_success_when_there_is_nothing_to_do(): void
    {
        $this->artisan('backfill:attendee-persons')
            ->expectsOutputToContain('Every attendee already has a person record.')
            ->assertExitCode(0);
    }

    public function test_an_existing_person_is_reused_rather_than_duplicated(): void
    {
        $existingPersonId = $this->makePerson('omar@example.test');
        $this->makeAttendees(1, email: 'omar@example.test');

        $this->artisan('backfill:attendee-persons')->assertExitCode(0);

        $this->assertSame(
            1,
            DB::table('persons')->where('account_id', $this->accountId)->count()
        );
        $this->assertSame(
            $existingPersonId,
            (int) DB::table('attendees')
                ->where('event_id', $this->eventId)
                ->value('person_id')
        );
    }

    public function test_another_accounts_person_is_not_reused(): void
    {
        $otherUser = User::factory()->withAccount()->create();
        $otherAccountId = (int) $otherUser->accounts()->first()->id;

        DB::table('persons')->insert([
            'short_id' => 'pn_'.Str::lower(Str::random(20)),
            'account_id' => $otherAccountId,
            'first_name' => 'Elsewhere',
            'email' => 'shared@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->makeAttendees(1, email: 'shared@example.test');

        $this->artisan('backfill:attendee-persons')->assertExitCode(0);

        $this->assertSame(
            1,
            DB::table('persons')->where('account_id', $this->accountId)->count(),
            'Matching across accounts would leak one tenant identities into another.'
        );
    }

    // ---------------------------------------------------------------- fixtures

    /**
     * @return array<int, int>
     */
    private function makeAttendees(int $count, string $email = 'default'): array
    {
        $ids = [];

        foreach (range(1, $count) as $index) {
            $resolvedEmail = $email === 'default'
                ? Str::lower(Str::random(12)).'@test.local'
                : $email;

            $ids[] = (int) DB::table('attendees')->insertGetId([
                'short_id' => 'at_'.Str::lower(Str::random(16)),
                'public_id' => 'A-'.Str::upper(Str::random(10)),
                'first_name' => 'Backfill',
                'last_name' => 'Attendee'.$index,
                'email' => $resolvedEmail,
                'order_id' => $this->orderId,
                'product_id' => $this->productId,
                'product_price_id' => $this->productPriceId,
                'event_id' => $this->eventId,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function makePerson(string $email): int
    {
        return (int) DB::table('persons')->insertGetId([
            'short_id' => 'pn_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Existing',
            'email' => $email,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeOrder(): int
    {
        return (int) DB::table('orders')->insertGetId([
            'short_id' => 'or_'.Str::lower(Str::random(16)),
            'public_id' => 'O-'.Str::upper(Str::random(10)),
            'event_id' => $this->eventId,
            'status' => 'COMPLETED',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'first_name' => 'Backfill',
            'last_name' => 'Buyer',
            'currency' => 'QAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function makeProduct(): array
    {
        $productId = (int) DB::table('products')->insertGetId([
            'title' => 'Backfill Ticket',
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

        return [$productId, $productPriceId];
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Backfill Organizer',
            'email' => 'bf-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Backfill Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(7),
            'timezone' => 'UTC',
            'currency' => 'QAR',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
