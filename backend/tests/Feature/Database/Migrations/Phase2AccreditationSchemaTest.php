<?php

namespace Tests\Feature\Database\Migrations;

use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pins the invariants of the accreditation and credential schema.
 *
 * The one that matters most is credentials_exactly_one_source: a credential must come
 * from exactly one place (an approved accreditation, or a paid attendee) so that a
 * single access engine serves ticket buyers, accredited press and staff without four
 * parallel code paths. Without the constraint, a credential with two sources or none
 * would be silently accepted and the access decision would become ambiguous.
 *
 * @see docs/arzo-master-plan/23-accreditation.md
 * @see docs/arzo-master-plan/24-access-control.md
 */
class Phase2AccreditationSchemaTest extends TestCase
{
    use DatabaseTransactions;

    private ?int $accountId = null;

    private ?int $eventId = null;

    private ?int $userId = null;

    public function test_accreditation_tables_exist(): void
    {
        foreach ([
            'accreditation_types',
            'accreditation_type_rules',
            'accreditations',
            'credentials',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_access_logs_link_to_a_credential(): void
    {
        $this->assertTrue(Schema::hasColumn('access_logs', 'credential_id'));
    }

    public function test_a_credential_from_an_attendee_is_accepted(): void
    {
        $attendeeId = $this->makeAttendee();

        $id = $this->insertCredential(['attendee_id' => $attendeeId]);

        $this->assertNotNull($id);
    }

    public function test_a_credential_from_an_accreditation_is_accepted(): void
    {
        $accreditationId = $this->makeAccreditation();

        $id = $this->insertCredential(['accreditation_id' => $accreditationId]);

        $this->assertNotNull($id);
    }

    public function test_a_credential_with_no_source_is_refused(): void
    {
        $this->expectExceptionMessageMatches('/credentials_exactly_one_source/');

        $this->insertCredential([]);
    }

    public function test_a_credential_with_two_sources_is_refused(): void
    {
        $attendeeId = $this->makeAttendee();
        $accreditationId = $this->makeAccreditation();

        $this->expectExceptionMessageMatches('/credentials_exactly_one_source/');

        $this->insertCredential([
            'attendee_id' => $attendeeId,
            'accreditation_id' => $accreditationId,
        ]);
    }

    public function test_credential_identifiers_are_unique_per_event(): void
    {
        $attendeeId = $this->makeAttendee();
        $identifier = Str::random(40);

        $this->insertCredential(['attendee_id' => $attendeeId], $identifier);

        $this->expectExceptionMessageMatches('/credentials_event_identifier_unique/');

        $this->insertCredential(['attendee_id' => $this->makeAttendee()], $identifier);
    }

    public function test_accreditation_type_codes_are_unique_per_event(): void
    {
        $this->makeAccreditationType('MEDIA');

        $this->expectExceptionMessageMatches('/accreditation_types_event_code_unique/');

        $this->makeAccreditationType('MEDIA');
    }

    public function test_one_person_cannot_apply_twice_for_the_same_type(): void
    {
        $typeId = $this->makeAccreditationType('VIP');
        $personId = $this->makePerson();

        $this->insertAccreditation($typeId, $personId);

        $this->expectExceptionMessageMatches('/accreditations_person_type_unique/');

        $this->insertAccreditation($typeId, $personId);
    }

    public function test_a_credential_can_replace_another(): void
    {
        // Lost-badge reissue: the old credential is revoked and the new one points back,
        // which is what clone detection and anti-passback need.
        $first = $this->insertCredential(['attendee_id' => $this->makeAttendee()]);

        $second = $this->insertCredential(
            ['attendee_id' => $this->makeAttendee(), 'replaces_credential_id' => $first]
        );

        $this->assertSame(
            $first,
            (int)DB::table('credentials')->where('id', $second)->value('replaces_credential_id')
        );
    }

    private function accountId(): int
    {
        if ($this->accountId === null) {
            $user = User::factory()->withAccount()->create();

            $this->userId = (int)$user->id;
            $this->accountId = (int)$user->accounts()->first()->id;
        }

        return $this->accountId;
    }

    private function makeEvent(): int
    {
        if ($this->eventId !== null) {
            return $this->eventId;
        }

        $accountId = $this->accountId();

        $organizerId = (int)DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Accreditation Organizer',
            'email' => 'org-'.Str::lower(Str::random(10)).'@test.local',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->eventId = (int)DB::table('events')->insertGetId([
            'title' => 'Accreditation Test Event',
            'account_id' => $accountId,
            'user_id' => $this->userId,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'ev_'.Str::lower(Str::random(16)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->eventId;
    }

    private function makePerson(): int
    {
        return (int)DB::table('persons')->insertGetId([
            'short_id' => 'pn_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId(),
            'first_name' => 'Test',
            'last_name' => 'Person',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAccreditationType(string $code): int
    {
        return (int)DB::table('accreditation_types')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(20)),
            'event_id' => $this->makeEvent(),
            'code' => $code,
            'name' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertAccreditation(int $typeId, int $personId): int
    {
        return (int)DB::table('accreditations')->insertGetId([
            'short_id' => 'ac_'.Str::lower(Str::random(20)),
            'event_id' => $this->makeEvent(),
            'person_id' => $personId,
            'accreditation_type_id' => $typeId,
            'status' => 'SUBMITTED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAccreditation(): int
    {
        return $this->insertAccreditation(
            $this->makeAccreditationType('TYPE'.Str::upper(Str::random(6))),
            $this->makePerson()
        );
    }

    private function makeAttendee(): int
    {
        $eventId = $this->makeEvent();

        $productId = (int)DB::table('products')->insertGetId([
            'title' => 'Accreditation Test Ticket',
            'event_id' => $eventId,
            'type' => 'FREE',
            'product_type' => 'TICKET',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productPriceId = (int)DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = (int)DB::table('orders')->insertGetId([
            'short_id' => 'or_'.Str::lower(Str::random(16)),
            'public_id' => 'O-'.Str::upper(Str::random(10)),
            'event_id' => $eventId,
            'status' => 'COMPLETED',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'first_name' => 'Buyer',
            'last_name' => 'One',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int)DB::table('attendees')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(16)),
            'public_id' => 'A-'.Str::upper(Str::random(10)),
            'first_name' => 'Test',
            'last_name' => 'Attendee',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $productPriceId,
            'event_id' => $eventId,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function insertCredential(array $source, ?string $identifier = null): int
    {
        $identifier ??= Str::random(40);

        return (int)DB::table('credentials')->insertGetId(array_merge([
            'short_id' => 'cr_'.Str::lower(Str::random(20)),
            'event_id' => $this->makeEvent(),
            'credential_type' => 'ATTENDEE',
            'status' => 'ACTIVE',
            'identifier' => $identifier,
            'identifier_hash' => hash('sha256', $identifier),
            'created_at' => now(),
            'updated_at' => now(),
        ], $source));
    }
}
