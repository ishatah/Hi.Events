<?php

namespace Tests\Unit\Services\Domain\Credential;

use HiEvents\Services\Domain\Credential\CredentialIdentifierService;
use Tests\TestCase;

class CredentialIdentifierServiceTest extends TestCase
{
    private CredentialIdentifierService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CredentialIdentifierService;
    }

    public function test_an_identifier_carries_a_version_prefix(): void
    {
        $this->assertStringStartsWith(
            'C1',
            $this->service->generate(),
            'A recognizer should not have to guess the format from the length.'
        );
    }

    public function test_an_identifier_is_upper_case_alphanumeric_only(): void
    {
        $identifier = $this->service->generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9A-Z]+$/',
            $identifier,
            'QR alphanumeric mode covers only 0-9 and A-Z; lower-case forces byte mode, and '
            .'40 bytes already fills a version-3 code at error-correction level M.'
        );
    }

    public function test_an_identifier_is_forty_characters(): void
    {
        $this->assertSame(40, strlen($this->service->generate()));
    }

    public function test_identifiers_do_not_repeat(): void
    {
        $identifiers = [];

        foreach (range(1, 200) as $ignored) {
            $identifiers[] = $this->service->generate();
        }

        $this->assertCount(
            200,
            array_unique($identifiers),
            'The identifier is the credential, so a collision is two people holding the same '
            .'badge.'
        );
    }

    public function test_an_identifier_is_not_derived_from_anything_guessable(): void
    {
        $first = $this->service->generate();
        $second = $this->service->generate();

        $this->assertNotSame(
            substr($first, 2, 10),
            substr($second, 2, 10),
            'A sequential or derived token would let somebody guess their way past a door.'
        );
    }

    // ---------------------------------------------------------------- resolution

    public function test_a_lower_case_scan_resolves_to_the_same_hash(): void
    {
        $identifier = $this->service->generate();

        $this->assertSame(
            $this->service->hash($identifier),
            $this->service->hash(strtolower($identifier)),
            'A keyboard-wedge scanner on a host with Caps Lock on inverts the case of '
            .'everything it types, and that must not read as a denial at the gate.'
        );
    }

    public function test_surrounding_whitespace_is_ignored(): void
    {
        $identifier = $this->service->generate();

        $this->assertSame(
            $this->service->hash($identifier),
            $this->service->hash("  {$identifier}\r\n"),
            'Wedge scanners append a terminator.'
        );
    }

    public function test_the_hash_is_not_the_token(): void
    {
        $identifier = $this->service->generate();

        $this->assertNotSame($identifier, $this->service->hash($identifier));
        $this->assertSame(64, strlen($this->service->hash($identifier)));
    }

    public function test_two_different_tokens_hash_differently(): void
    {
        $this->assertNotSame(
            $this->service->hash($this->service->generate()),
            $this->service->hash($this->service->generate())
        );
    }

    // ---------------------------------------------------------------- recognition

    public function test_a_generated_identifier_is_recognised(): void
    {
        $this->assertTrue($this->service->looksLikeCredential($this->service->generate()));
    }

    public function test_a_lower_case_or_padded_scan_is_still_recognised(): void
    {
        $identifier = $this->service->generate();

        $this->assertTrue($this->service->looksLikeCredential(strtolower($identifier)));
        $this->assertTrue($this->service->looksLikeCredential(" {$identifier} "));
    }

    public function test_a_legacy_ticket_code_is_not_mistaken_for_a_credential(): void
    {
        $this->assertFalse(
            $this->service->looksLikeCredential('A-ABC123XYZ'),
            'Tickets already emailed carry A- codes and cannot be recalled, so the two shapes '
            .'have to stay distinguishable.'
        );
    }

    public function test_a_random_barcode_is_not_mistaken_for_a_credential(): void
    {
        foreach (['', '12345', 'C1', 'C1SHORT', str_repeat('X', 40), 'C1'.str_repeat('X', 37)] as $candidate) {
            $this->assertFalse(
                $this->service->looksLikeCredential($candidate),
                sprintf('%s should not be recognised as a credential.', var_export($candidate, true))
            );
        }
    }

    public function test_a_token_with_punctuation_is_not_recognised(): void
    {
        $this->assertFalse($this->service->looksLikeCredential('C1'.str_repeat('A', 36).'-!'));
    }
}
