<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Auth;

/**
 * RFC 6238 time-based one-time passwords.
 *
 * Implemented directly rather than pulled in as a dependency: the algorithm is a HMAC and a
 * truncation, and the parts that actually decide whether this is secure — a constant-time
 * comparison, a bounded clock-skew window, and refusing a code that has already been used —
 * are decisions that should be visible here rather than inherited from a package's defaults.
 *
 * Compatible with Google Authenticator, Authy, 1Password and the rest: SHA-1, 6 digits, 30
 * second steps. SHA-1 is specified by RFC 6238 and is not a weakness in this construction —
 * HMAC-SHA1 has no practical break, and the output is truncated to six digits anyway.
 *
 * @see docs/arzo-master-plan/101-enterprise.md
 */
class TotpService
{
    private const DIGITS = 6;

    private const PERIOD_SECONDS = 30;

    private const ALGORITHM = 'sha1';

    /**
     * How many steps either side of now are accepted.
     *
     * One step, so a code stays valid for at most 90 seconds. Wider windows are common and
     * wrong: each extra step multiplies the number of codes an attacker may guess, and phones
     * are rarely more than a few seconds out.
     */
    private const SKEW_STEPS = 1;

    private const SECRET_BYTES = 20;

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(self::SECRET_BYTES));
    }

    /**
     * The otpauth:// URI an authenticator app reads from a QR code.
     *
     * The issuer appears twice by convention — once as a label prefix and once as a parameter
     * — because older apps read only one of the two.
     */
    public function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($accountName);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper(self::ALGORITHM),
            'digits' => self::DIGITS,
            'period' => self::PERIOD_SECONDS,
        ]);
    }

    /**
     * Verifies a submitted code and returns the time step it matched, or null.
     *
     * The step is returned rather than a bare boolean so the caller can record it and refuse
     * the same code a second time. A code read over somebody's shoulder otherwise stays usable
     * for the rest of its window.
     *
     * @param  int|null  $lastUsedTimestep  the step this user last authenticated with
     */
    public function verify(
        string $secret,
        string $code,
        ?int $lastUsedTimestep = null,
        ?int $now = null,
    ): ?int {
        $submitted = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($submitted) !== self::DIGITS) {
            return null;
        }

        $currentStep = intdiv($now ?? time(), self::PERIOD_SECONDS);

        for ($offset = -self::SKEW_STEPS; $offset <= self::SKEW_STEPS; $offset++) {
            $step = $currentStep + $offset;

            if ($lastUsedTimestep !== null && $step <= $lastUsedTimestep) {
                continue;
            }

            // hash_equals rather than ===, so the time taken does not reveal how much of the
            // code was right.
            if (hash_equals($this->codeForStep($secret, $step), $submitted)) {
                return $step;
            }
        }

        return null;
    }

    public function codeForStep(string $secret, int $step): string
    {
        $binarySecret = $this->base32Decode($secret);

        if ($binarySecret === '') {
            return '';
        }

        $hash = hash_hmac(self::ALGORITHM, pack('J', $step), $binarySecret, true);

        // Dynamic truncation, per RFC 4226 section 5.3: the low nibble of the last byte picks
        // where in the digest to read from.
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad(
            (string) ($binary % (10 ** self::DIGITS)),
            self::DIGITS,
            '0',
            STR_PAD_LEFT
        );
    }

    private function base32Encode(string $binary): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $normalised = strtoupper(rtrim($encoded, '='));
        $bits = '';

        foreach (str_split($normalised) as $character) {
            $index = strpos($alphabet, $character);

            if ($index === false) {
                return '';
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}
