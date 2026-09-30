<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Auth;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Auth\DTO\MfaEnrolmentDTO;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Enrolling, confirming and verifying a second factor.
 *
 * Enrolment is two steps on purpose. The secret is stored unconfirmed, and only a code the
 * user has actually produced from their authenticator confirms it — otherwise a failed
 * enrolment (wrong clock, closed tab, misread QR) locks somebody out of their own account with
 * a factor they cannot satisfy.
 *
 * Recovery codes are issued at confirmation rather than on request, because the moment somebody
 * needs one is the moment they have lost the phone they would have used to ask.
 *
 * @see docs/arzo-master-plan/101-enterprise.md
 */
class MfaService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly TotpService $totpService,
        private readonly Encrypter $encrypter,
    ) {}

    /**
     * Starts enrolment and returns the secret to display once.
     *
     * @throws ResourceConflictException
     */
    public function beginEnrolment(int $userId, string $issuer): MfaEnrolmentDTO
    {
        $user = $this->findUser($userId);

        if ($user->mfa_confirmed_at !== null) {
            throw new ResourceConflictException(
                __('Two-factor authentication is already set up. Turn it off first to enrol a new device.')
            );
        }

        $secret = $this->totpService->generateSecret();

        $this->databaseManager->table('users')
            ->where('id', $userId)
            ->update([
                'mfa_secret_encrypted' => $this->encrypter->encryptString($secret),
                // Deliberately left unconfirmed, and any earlier replay marker cleared: a new
                // secret has its own sequence of time steps.
                'mfa_confirmed_at' => null,
                'mfa_last_used_timestep' => null,
                'updated_at' => now(),
            ]);

        return new MfaEnrolmentDTO(
            secret: $secret,
            provisioningUri: $this->totpService->provisioningUri($secret, (string) $user->email, $issuer),
            recoveryCodes: [],
        );
    }

    /**
     * Confirms enrolment with a code from the authenticator, and issues recovery codes.
     *
     * @throws ResourceConflictException
     */
    public function confirmEnrolment(int $userId, string $code): MfaEnrolmentDTO
    {
        return $this->databaseManager->transaction(function () use ($userId, $code): MfaEnrolmentDTO {
            $user = $this->findUser($userId);

            if ($user->mfa_secret_encrypted === null) {
                throw new ResourceConflictException(__('Start two-factor setup before confirming it.'));
            }

            if ($user->mfa_confirmed_at !== null) {
                throw new ResourceConflictException(__('Two-factor authentication is already set up.'));
            }

            $secret = $this->encrypter->decryptString((string) $user->mfa_secret_encrypted);
            $step = $this->totpService->verify($secret, $code);

            if ($step === null) {
                throw new ResourceConflictException(
                    __('That code is not valid. Check your authenticator app and try again.')
                );
            }

            $this->databaseManager->table('users')
                ->where('id', $userId)
                ->update([
                    'mfa_confirmed_at' => now(),
                    'mfa_last_used_timestep' => $step,
                    'updated_at' => now(),
                ]);

            return new MfaEnrolmentDTO(
                secret: $secret,
                provisioningUri: '',
                recoveryCodes: $this->issueRecoveryCodes($userId),
            );
        });
    }

    /**
     * Verifies a factor at login: a TOTP code, or a recovery code.
     *
     * @throws ResourceConflictException
     */
    public function verify(int $userId, string $code): bool
    {
        return $this->databaseManager->transaction(function () use ($userId, $code): bool {
            $user = $this->databaseManager->table('users')
                ->where('id', $userId)
                ->lockForUpdate()
                ->first();

            if ($user === null || $user->mfa_confirmed_at === null) {
                throw new ResourceConflictException(__('Two-factor authentication is not set up.'));
            }

            $secret = $this->encrypter->decryptString((string) $user->mfa_secret_encrypted);

            $step = $this->totpService->verify(
                secret: $secret,
                code: $code,
                lastUsedTimestep: $user->mfa_last_used_timestep !== null
                    ? (int) $user->mfa_last_used_timestep
                    : null,
            );

            if ($step !== null) {
                // Recorded under the same lock, so two requests racing with one code cannot
                // both succeed.
                $this->databaseManager->table('users')
                    ->where('id', $userId)
                    ->update(['mfa_last_used_timestep' => $step, 'updated_at' => now()]);

                return true;
            }

            return $this->consumeRecoveryCode($userId, $code);
        });
    }

    /**
     * @throws ResourceConflictException
     */
    public function disable(int $userId, string $code): void
    {
        if (! $this->verify($userId, $code)) {
            throw new ResourceConflictException(
                __('That code is not valid, so two-factor authentication was left on.')
            );
        }

        $this->databaseManager->transaction(function () use ($userId): void {
            $this->databaseManager->table('users')
                ->where('id', $userId)
                ->update([
                    'mfa_secret_encrypted' => null,
                    'mfa_confirmed_at' => null,
                    'mfa_last_used_timestep' => null,
                    'updated_at' => now(),
                ]);

            // Deleted rather than kept: a recovery code for a factor that no longer exists is
            // a credential nobody is watching.
            $this->databaseManager->table('mfa_recovery_codes')
                ->where('user_id', $userId)
                ->delete();
        });
    }

    public function isEnabled(int $userId): bool
    {
        return $this->databaseManager->table('users')
            ->where('id', $userId)
            ->whereNotNull('mfa_confirmed_at')
            ->exists();
    }

    /**
     * Whether any account this user belongs to insists on a second factor.
     *
     * Any rather than all: an organisation that requires MFA is not satisfied by the user
     * having declined it elsewhere.
     */
    public function isRequiredFor(int $userId): bool
    {
        return $this->databaseManager->table('account_users')
            ->join('accounts', 'accounts.id', '=', 'account_users.account_id')
            ->where('account_users.user_id', $userId)
            ->where('accounts.require_mfa', true)
            ->whereNull('account_users.deleted_at')
            ->exists();
    }

    public function remainingRecoveryCodes(int $userId): int
    {
        return $this->databaseManager->table('mfa_recovery_codes')
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->count();
    }

    /**
     * @return array<int, string>
     */
    private function issueRecoveryCodes(int $userId): array
    {
        $this->databaseManager->table('mfa_recovery_codes')->where('user_id', $userId)->delete();

        $codes = [];
        $rows = [];

        foreach (range(1, self::RECOVERY_CODE_COUNT) as $ignored) {
            // Grouped for transcription: somebody reads these off a screen and onto paper.
            $code = Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5));

            $codes[] = $code;
            $rows[] = [
                'user_id' => $userId,
                'code_hash' => hash('sha256', $this->normaliseRecoveryCode($code)),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->databaseManager->table('mfa_recovery_codes')->insert($rows);

        return $codes;
    }

    private function consumeRecoveryCode(int $userId, string $code): bool
    {
        $hash = hash('sha256', $this->normaliseRecoveryCode($code));

        $consumed = $this->databaseManager->table('mfa_recovery_codes')
            ->where('user_id', $userId)
            ->where('code_hash', $hash)
            ->whereNull('used_at')
            ->update(['used_at' => now(), 'updated_at' => now()]);

        return $consumed > 0;
    }

    /**
     * Somebody copying a code off paper will get the case or the dash wrong.
     */
    private function normaliseRecoveryCode(string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }

    /**
     * @throws ResourceConflictException
     */
    private function findUser(int $userId): object
    {
        $user = $this->databaseManager->table('users')->where('id', $userId)->first();

        if ($user === null) {
            throw new ResourceConflictException(__('That user could not be found.'));
        }

        return $user;
    }
}
