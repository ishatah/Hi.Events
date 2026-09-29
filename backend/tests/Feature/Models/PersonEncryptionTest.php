<?php

namespace Tests\Feature\Models;

use HiEvents\Models\Person;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\PersonRepositoryInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Identity documents and dates of birth must not sit in the database in plaintext.
 *
 * These are the fields an accreditation type can demand, and the ones that cause real harm
 * if a backup, a replica or a support query exposes them.
 *
 * @see docs/arzo-master-plan/65-privacy-gdpr.md
 */
class PersonEncryptionTest extends TestCase
{
    use DatabaseTransactions;

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withAccount()->create();
        $this->accountId = (int) $user->accounts()->first()->id;
    }

    public function test_identity_fields_are_not_stored_in_plaintext(): void
    {
        $person = $this->makePerson([
            'id_document_number' => 'QA1234567',
            'id_document_type' => 'PASSPORT',
            'date_of_birth' => '1990-05-12',
        ]);

        $raw = DB::table('persons')->where('id', $person->id)->first();

        $this->assertStringNotContainsString('QA1234567', (string) $raw->id_document_number);
        $this->assertStringNotContainsString('PASSPORT', (string) $raw->id_document_type);
        $this->assertStringNotContainsString('1990-05-12', (string) $raw->date_of_birth);
    }

    public function test_identity_fields_read_back_correctly(): void
    {
        $person = $this->makePerson([
            'id_document_number' => 'QA1234567',
            'id_document_type' => 'PASSPORT',
            'date_of_birth' => '1990-05-12',
        ]);

        $reloaded = Person::find($person->id);

        $this->assertSame('QA1234567', $reloaded->id_document_number);
        $this->assertSame('PASSPORT', $reloaded->id_document_type);
        $this->assertSame('1990-05-12', $reloaded->date_of_birth);
    }

    public function test_the_repository_layer_decrypts_too(): void
    {
        $person = $this->makePerson(['id_document_number' => 'QA7654321']);

        $domainObject = app(PersonRepositoryInterface::class)->findById((int) $person->id);

        $this->assertSame(
            'QA7654321',
            $domainObject->getIdDocumentNumber(),
            'Reading through a repository must not surface ciphertext.'
        );
    }

    public function test_a_person_without_identity_documents_still_works(): void
    {
        $person = $this->makePerson();

        $reloaded = Person::find($person->id);

        $this->assertNull($reloaded->id_document_number);
        $this->assertNull($reloaded->date_of_birth);
    }

    public function test_an_identity_field_cannot_be_matched_by_a_plain_where(): void
    {
        $this->makePerson(['id_document_number' => 'QA5555555']);

        $this->assertSame(
            0,
            DB::table('persons')->where('id_document_number', 'QA5555555')->count(),
            'Encryption is not searchable by design; anything needing lookup must hash '
            .'separately rather than quietly reverting to plaintext.'
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePerson(array $attributes = []): Person
    {
        return Person::create(array_merge([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Encrypted',
            'last_name' => 'Person',
            'email' => Str::lower(Str::random(10)).'@test.local',
        ], $attributes));
    }
}
