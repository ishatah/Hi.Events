<?php

namespace Tests\Unit\Services\Domain\Auth;

use HiEvents\Services\Domain\Auth\TotpService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TotpServiceTest extends TestCase
{
    /**
     * The base32 of the ASCII secret "12345678901234567890" that RFC 6238 Appendix B uses.
     */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private TotpService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TotpService;
    }

    /**
     * The published vectors. A TOTP that disagrees with these disagrees with every
     * authenticator app, which is worse than having no second factor at all — it locks users
     * out while appearing to work.
     */
    #[DataProvider('rfc6238VectorProvider')]
    public function test_it_matches_the_rfc_6238_test_vectors(int $unixTime, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->service->codeForStep(self::RFC_SECRET, intdiv($unixTime, 30))
        );
    }

    public static function rfc6238VectorProvider(): array
    {
        return [
            't=59' => [59, '287082'],
            't=1111111109' => [1111111109, '081804'],
            't=1111111111' => [1111111111, '050471'],
            't=1234567890' => [1234567890, '005924'],
            't=2000000000' => [2000000000, '279037'],
        ];
    }

    public function test_a_generated_secret_is_base32_and_long_enough(): void
    {
        $secret = $this->service->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertSame(
            32,
            strlen($secret),
            '20 random bytes, which is what RFC 4226 recommends as a minimum.'
        );
    }

    public function test_secrets_do_not_repeat(): void
    {
        $secrets = [];

        foreach (range(1, 50) as $ignored) {
            $secrets[] = $this->service->generateSecret();
        }

        $this->assertCount(50, array_unique($secrets));
    }

    public function test_the_provisioning_uri_carries_what_an_authenticator_needs(): void
    {
        $uri = $this->service->provisioningUri('ABCDEFGHIJKLMNOP', 'layla@example.test', 'ARZO');

        $this->assertStringStartsWith('otpauth://totp/ARZO:layla%40example.test?', $uri);
        $this->assertStringContainsString('secret=ABCDEFGHIJKLMNOP', $uri);
        $this->assertStringContainsString('issuer=ARZO', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }

    // ---------------------------------------------------------------- verification

    public function test_the_current_code_verifies(): void
    {
        $now = 1_700_000_000;
        $code = $this->service->codeForStep(self::RFC_SECRET, intdiv($now, 30));

        $this->assertNotNull($this->service->verify(self::RFC_SECRET, $code, null, $now));
    }

    public function test_a_code_from_the_previous_step_still_verifies(): void
    {
        $now = 1_700_000_000;
        $code = $this->service->codeForStep(self::RFC_SECRET, intdiv($now, 30) - 1);

        $this->assertNotNull(
            $this->service->verify(self::RFC_SECRET, $code, null, $now),
            'Phones drift by a few seconds, and somebody typing a code takes a moment.'
        );
    }

    public function test_a_code_two_steps_old_is_refused(): void
    {
        $now = 1_700_000_000;
        $code = $this->service->codeForStep(self::RFC_SECRET, intdiv($now, 30) - 2);

        $this->assertNull(
            $this->service->verify(self::RFC_SECRET, $code, null, $now),
            'Each extra step of tolerance multiplies the codes an attacker may guess.'
        );
    }

    public function test_the_same_code_cannot_be_used_twice(): void
    {
        $now = 1_700_000_000;
        $step = intdiv($now, 30);
        $code = $this->service->codeForStep(self::RFC_SECRET, $step);

        $firstUse = $this->service->verify(self::RFC_SECRET, $code, null, $now);

        $this->assertSame($step, $firstUse);
        $this->assertNull(
            $this->service->verify(self::RFC_SECRET, $code, $firstUse, $now),
            'A code read over somebody shoulder otherwise stays usable for the rest of its '
            .'window.'
        );
    }

    public function test_an_earlier_step_is_refused_once_a_later_one_was_used(): void
    {
        $now = 1_700_000_000;
        $currentStep = intdiv($now, 30);
        $previousCode = $this->service->codeForStep(self::RFC_SECRET, $currentStep - 1);

        $this->assertNull(
            $this->service->verify(self::RFC_SECRET, $previousCode, $currentStep, $now),
            'Replay protection has to cover the skew window, not just the exact step.'
        );
    }

    public function test_a_wrong_code_is_refused(): void
    {
        $this->assertNull($this->service->verify(self::RFC_SECRET, '000000', null, 1_700_000_000));
    }

    public function test_a_code_for_a_different_secret_is_refused(): void
    {
        $now = 1_700_000_000;
        $otherSecret = $this->service->generateSecret();
        $code = $this->service->codeForStep($otherSecret, intdiv($now, 30));

        $this->assertNull($this->service->verify(self::RFC_SECRET, $code, null, $now));
    }

    #[DataProvider('malformedCodeProvider')]
    public function test_a_malformed_code_is_refused(string $code): void
    {
        $this->assertNull($this->service->verify(self::RFC_SECRET, $code, null, 1_700_000_000));
    }

    public static function malformedCodeProvider(): array
    {
        return [
            'empty' => [''],
            'too short' => ['12345'],
            'too long' => ['1234567'],
            'letters' => ['abcdef'],
            'sql' => ["' OR 1=1--"],
        ];
    }

    public function test_spaces_and_dashes_in_a_typed_code_are_tolerated(): void
    {
        $now = 1_700_000_000;
        $code = $this->service->codeForStep(self::RFC_SECRET, intdiv($now, 30));
        $typed = substr($code, 0, 3).' '.substr($code, 3);

        $this->assertNotNull(
            $this->service->verify(self::RFC_SECRET, $typed, null, $now),
            'Authenticator apps display codes as two groups of three.'
        );
    }

    public function test_an_invalid_secret_never_authenticates(): void
    {
        $this->assertNull($this->service->verify('not-valid-base32!!', '123456', null, 1_700_000_000));
        $this->assertSame('', $this->service->codeForStep('not-valid-base32!!', 1));
    }

    public function test_codes_change_between_steps(): void
    {
        $step = intdiv(1_700_000_000, 30);

        $this->assertNotSame(
            $this->service->codeForStep(self::RFC_SECRET, $step),
            $this->service->codeForStep(self::RFC_SECRET, $step + 1)
        );
    }
}
