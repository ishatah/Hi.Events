<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Credential;

use Illuminate\Support\Str;

/**
 * Generates and normalises the token a scanner reads off a badge.
 *
 * One place, because generation and resolution have to agree exactly: a normaliser that
 * differs from the generator by one character turns every badge into a denial at the gate.
 *
 * Three properties, all load-bearing:
 *
 * Opaque and random, because the identifier is the credential — a sequential or email-derived
 * token would let somebody guess their way past a door.
 *
 * Versioned prefix, so the format can change later without a recognizer having to guess from
 * length. Two characters, which QR alphanumeric mode encodes at the same density as the rest.
 *
 * Upper-case, because QR alphanumeric mode covers only 0-9, A-Z and a few symbols; lower-case
 * forces byte mode. At error-correction level M a version-3 code holds 61 alphanumeric
 * characters but only 42 bytes, and 40 lower-case characters already use 40 of those 42 — so
 * any prefix at all would spill into a denser version-4 code that reads more slowly on a phone
 * camera. Entropy is unaffected: 40 characters from 36 symbols is over 200 bits.
 *
 * @see docs/arzo-master-plan/38-scanner-platform.md
 * @see docs/arzo-master-plan/136-master-backlog.md ARZ-313
 */
class CredentialIdentifierService
{
    public const VERSION_PREFIX = 'C1';

    private const RANDOM_LENGTH = 38;

    public function generate(): string
    {
        return self::VERSION_PREFIX.Str::upper(Str::random(self::RANDOM_LENGTH));
    }

    /**
     * Normalises a scanned token before it is hashed.
     *
     * A keyboard-wedge scanner on a host with Caps Lock on inverts the case of everything it
     * types, so resolution has to be case-insensitive whatever the badge says. Surrounding
     * whitespace comes from wedge scanners appending a terminator.
     */
    public function normalise(string $scanned): string
    {
        return Str::upper(trim($scanned));
    }

    /**
     * The value stored and compared against. Never the raw token: a leaked database should not
     * hand somebody a working badge.
     */
    public function hash(string $scanned): string
    {
        return hash('sha256', $this->normalise($scanned));
    }
}
