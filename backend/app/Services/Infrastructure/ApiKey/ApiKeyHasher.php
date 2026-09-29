<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\ApiKey;

use Illuminate\Support\Str;

/**
 * One hashing scheme and one prefix convention for every machine principal.
 *
 * API keys and device keys live in different tables but must be issued and verified
 * identically, or the two stores drift and a key that authenticates in one path fails in
 * the other.
 *
 * The prefix is carried in the key itself and stored alongside the hash. It is what lets
 * the authenticator find the candidate row without scanning every key, and it is what makes
 * a leaked key recognisable in logs and to secret scanners.
 *
 * @see docs/arzo-master-plan/48-api-platform.md
 */
class ApiKeyHasher
{
    public const API_KEY_PREFIX = 'arzo_';

    public const DEVICE_KEY_PREFIX = 'arzod_';

    private const PREFIX_ENTROPY_CHARS = 12;

    private const SECRET_ENTROPY_CHARS = 48;

    /**
     * @return array{plaintext: string, prefix: string, hash: string}
     */
    public function generate(string $keyType = self::API_KEY_PREFIX): array
    {
        $identifier = Str::lower(Str::random(self::PREFIX_ENTROPY_CHARS));
        $secret = Str::random(self::SECRET_ENTROPY_CHARS);

        $prefix = $keyType.$identifier;

        return [
            'plaintext' => $prefix.'_'.$secret,
            'prefix' => $prefix,
            // Not bcrypt: this is verified on every API request, and a deliberately slow
            // hash would make the authenticator the slowest part of the stack. The secret
            // is 48 random characters rather than a human password, so it is not
            // vulnerable to the dictionary attack bcrypt exists to slow down.
            'hash' => hash('sha256', $secret),
        ];
    }

    /**
     * Splits a presented key into the part used for lookup and the part that is verified.
     *
     * @return array{prefix: string, secret: string}|null
     */
    public function parse(string $plaintext): ?array
    {
        $separator = strrpos($plaintext, '_');

        if ($separator === false || $separator === strlen($plaintext) - 1) {
            return null;
        }

        $prefix = substr($plaintext, 0, $separator);
        $secret = substr($plaintext, $separator + 1);

        if (! $this->isRecognisedPrefix($prefix)) {
            return null;
        }

        return ['prefix' => $prefix, 'secret' => $secret];
    }

    public function verify(string $secret, string $storedHash): bool
    {
        // Constant time: a plain === leaks how much of the hash matched through timing,
        // which is enough to reconstruct it byte by byte.
        return hash_equals($storedHash, hash('sha256', $secret));
    }

    public function isDeviceKey(string $prefix): bool
    {
        return str_starts_with($prefix, self::DEVICE_KEY_PREFIX);
    }

    private function isRecognisedPrefix(string $prefix): bool
    {
        // Device keys are checked first: 'arzod_' also starts with 'arzo_', so testing the
        // shorter prefix first would classify every device key as an API key.
        return str_starts_with($prefix, self::DEVICE_KEY_PREFIX)
            || str_starts_with($prefix, self::API_KEY_PREFIX);
    }
}
