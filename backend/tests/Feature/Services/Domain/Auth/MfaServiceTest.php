<?php

namespace Tests\Feature\Services\Domain\Auth;

use HiEvents\Models\User;
use HiEvents\Services\Domain\Auth\MfaService;
use HiEvents\Services\Domain\Auth\TotpService;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MfaServiceTest extends TestCase
{
    use DatabaseTransactions;

    private MfaService $service;

    private TotpService $totp;

    private Encrypter $encrypter;

    private int $userId;

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MfaService::class);
        $this->totp = app(TotpService::class);
        $this->encrypter = app(Encrypter::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
    }

    // ---------------------------------------------------------------- enrolment

    public function test_enrolment_returns_a_secret_and_a_provisioning_uri(): void
    {
        $enrolment = $this->service->beginEnrolment($this->userId, 'ARZO');

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $enrolment->secret);
        $this->assertStringStartsWith('otpauth://totp/ARZO:', $enrolment->provisioningUri);
        $this->assertSame([], $enrolment->recoveryCodes);
    }

    public function test_the_secret_is_encrypted_at_rest(): void
    {
        $enrolment = $this->service->beginEnrolment($this->userId, 'ARZO');

        $stored = (string) DB::table('users')->where('id', $this->userId)->value('mfa_secret_encrypted');

        $this->assertNotSame(
            $enrolment->secret,
            $stored,
            'A factor stored in plaintext beside the password hash it defends is not a second '
            .'factor.'
        );
        $this->assertSame($enrolment->secret, $this->encrypter->decryptString($stored));
    }

    public function test_enrolment_is_not_active_until_confirmed(): void
    {
        $this->service->beginEnrolment($this->userId, 'ARZO');

        $this->assertFalse(
            $this->service->isEnabled($this->userId),
            'A stored but unproved secret would lock somebody out with a factor they cannot '
            .'satisfy.'
        );
        $this->assertNull(DB::table('users')->where('id', $this->userId)->value('mfa_confirmed_at'));
    }

    public function test_confirming_with_a_valid_code_enables_it_and_issues_recovery_codes(): void
    {
        $enrolment = $this->service->beginEnrolment($this->userId, 'ARZO');

        $confirmed = $this->service->confirmEnrolment($this->userId, $this->currentCode($enrolment->secret));

        $this->assertTrue($this->service->isEnabled($this->userId));
        $this->assertCount(8, $confirmed->recoveryCodes);
        $this->assertSame(8, $this->service->remainingRecoveryCodes($this->userId));
    }

    public function test_confirming_with_a_wrong_code_is_refused(): void
    {
        $this->service->beginEnrolment($this->userId, 'ARZO');

        $this->expectExceptionMessageMatches('/not valid/');
        $this->service->confirmEnrolment($this->userId, '000000');
    }

    public function test_confirming_without_starting_is_refused(): void
    {
        $this->expectExceptionMessageMatches('/Start two-factor setup/');
        $this->service->confirmEnrolment($this->userId, '123456');
    }

    public function test_enrolling_twice_is_refused(): void
    {
        $enrolment = $this->service->beginEnrolment($this->userId, 'ARZO');
        $this->service->confirmEnrolment($this->userId, $this->currentCode($enrolment->secret));

        $this->expectExceptionMessageMatches('/already set up/');
        $this->service->beginEnrolment($this->userId, 'ARZO');
    }

    public function test_recovery_codes_are_stored_hashed(): void
    {
        $enrolment = $this->service->beginEnrolment($this->userId, 'ARZO');
        $confirmed = $this->service->confirmEnrolment($this->userId, $this->currentCode($enrolment->secret));

        $stored = DB::table('mfa_recovery_codes')->where('user_id', $this->userId)->pluck('code_hash');

        foreach ($confirmed->recoveryCodes as $code) {
            $this->assertNotContains(
                $code,
                $stored->all(),
                'A recovery code only ever needs comparing, so there is no reason to be able '
                .'to read it back.'
            );
        }
    }

    // ---------------------------------------------------------------- verification

    public function test_a_valid_code_verifies(): void
    {
        $secret = $this->enable();

        $this->assertTrue($this->service->verify($this->userId, $this->currentCode($secret)));
    }

    public function test_the_same_code_cannot_be_used_twice(): void
    {
        $secret = $this->enable();
        $code = $this->currentCode($secret);

        $this->assertTrue($this->service->verify($this->userId, $code));
        $this->assertFalse(
            $this->service->verify($this->userId, $code),
            'The used step is recorded under the same lock, so two requests racing with one '
            .'code cannot both succeed.'
        );
    }

    public function test_a_wrong_code_is_refused(): void
    {
        $this->enable();

        $this->assertFalse($this->service->verify($this->userId, '000000'));
    }

    public function test_verifying_without_mfa_set_up_is_refused(): void
    {
        $this->expectExceptionMessageMatches('/not set up/');
        $this->service->verify($this->userId, '123456');
    }

    // ---------------------------------------------------------------- recovery codes

    public function test_a_recovery_code_verifies_when_the_phone_is_gone(): void
    {
        $codes = $this->enableAndGetRecoveryCodes();

        $this->assertTrue($this->service->verify($this->userId, $codes[0]));
        $this->assertSame(7, $this->service->remainingRecoveryCodes($this->userId));
    }

    public function test_a_recovery_code_is_single_use(): void
    {
        $codes = $this->enableAndGetRecoveryCodes();

        $this->assertTrue($this->service->verify($this->userId, $codes[0]));
        $this->assertFalse(
            $this->service->verify($this->userId, $codes[0]),
            'A reusable recovery code is a permanent password written on paper.'
        );
    }

    public function test_a_recovery_code_is_accepted_however_it_was_transcribed(): void
    {
        $codes = $this->enableAndGetRecoveryCodes();

        $mangled = strtolower(str_replace('-', ' ', $codes[0]));

        $this->assertTrue(
            $this->service->verify($this->userId, $mangled),
            'Somebody copying a code off paper will get the case or the dash wrong.'
        );
    }

    public function test_another_users_recovery_code_does_not_work(): void
    {
        $codes = $this->enableAndGetRecoveryCodes();

        $other = User::factory()->withAccount()->create();
        $otherSecret = $this->service->beginEnrolment((int) $other->id, 'ARZO')->secret;
        $this->service->confirmEnrolment((int) $other->id, $this->currentCode($otherSecret));

        $this->assertFalse(
            $this->service->verify((int) $other->id, $codes[0]),
            'Recovery codes are scoped to the user who was issued them.'
        );
    }

    // ---------------------------------------------------------------- disabling

    public function test_disabling_requires_a_valid_code(): void
    {
        $this->enable();

        $this->expectExceptionMessageMatches('/left on/');
        $this->service->disable($this->userId, '000000');
    }

    public function test_disabling_clears_the_secret_and_the_recovery_codes(): void
    {
        $secret = $this->enable();

        $this->service->disable($this->userId, $this->currentCode($secret));

        $user = DB::table('users')->where('id', $this->userId)->first();

        $this->assertNull($user->mfa_secret_encrypted);
        $this->assertNull($user->mfa_confirmed_at);
        $this->assertFalse($this->service->isEnabled($this->userId));
        $this->assertSame(
            0,
            DB::table('mfa_recovery_codes')->where('user_id', $this->userId)->count(),
            'A recovery code for a factor that no longer exists is a credential nobody is '
            .'watching.'
        );
    }

    public function test_it_can_be_re_enrolled_after_disabling(): void
    {
        $secret = $this->enable();
        $this->service->disable($this->userId, $this->currentCode($secret));

        $fresh = $this->service->beginEnrolment($this->userId, 'ARZO');

        $this->assertNotSame($secret, $fresh->secret);
        $this->assertNull(
            DB::table('users')->where('id', $this->userId)->value('mfa_last_used_timestep'),
            'A new secret has its own sequence of time steps, so the old replay marker would '
            .'refuse valid codes.'
        );
    }

    // ---------------------------------------------------------------- enforcement

    public function test_an_account_can_require_a_second_factor(): void
    {
        $this->assertFalse($this->service->isRequiredFor($this->userId));

        DB::table('accounts')->where('id', $this->accountId)->update(['require_mfa' => true]);

        $this->assertTrue($this->service->isRequiredFor($this->userId));
    }

    public function test_one_account_requiring_it_is_enough(): void
    {
        $strictAccountId = (int) DB::table('accounts')->insertGetId([
            'name' => 'Strict Co',
            'email' => 'strict-'.uniqid().'@test.local',
            'currency_code' => 'QAR',
            'timezone' => 'UTC',
            'short_id' => 'ac_'.uniqid(),
            'require_mfa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('account_users')->insert([
            'user_id' => $this->userId,
            'account_id' => $strictAccountId,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(
            $this->service->isRequiredFor($this->userId),
            'An organisation that requires MFA is not satisfied by the user having declined it '
            .'elsewhere.'
        );
    }

    // ---------------------------------------------------------------- fixtures

    private function currentCode(string $secret): string
    {
        return $this->totp->codeForStep($secret, intdiv(time(), 30));
    }

    private function enable(): string
    {
        $enrolment = $this->service->beginEnrolment($this->userId, 'ARZO');
        $this->service->confirmEnrolment($this->userId, $this->currentCode($enrolment->secret));

        // Cleared so a test verifying immediately afterwards is not refused as a replay of
        // the code that confirmed enrolment.
        DB::table('users')->where('id', $this->userId)->update(['mfa_last_used_timestep' => null]);

        return $enrolment->secret;
    }

    /**
     * @return array<int, string>
     */
    private function enableAndGetRecoveryCodes(): array
    {
        $enrolment = $this->service->beginEnrolment($this->userId, 'ARZO');
        $confirmed = $this->service->confirmEnrolment($this->userId, $this->currentCode($enrolment->secret));

        return $confirmed->recoveryCodes;
    }
}
