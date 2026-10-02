<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\TokenGenerator;

/**
 * Hashes the single-use tokens that are emailed as links, so the stored row cannot be
 * replayed by anyone who reads the database, a backup or a query log.
 *
 * A fast hash is correct here: these tokens are 30 hex characters from a CSPRNG, so there
 * is no low-entropy secret to slow an attacker down over.
 *
 * @see docs/arzo-master-plan/136-master-backlog.md ARZ-327
 */
class EmailedTokenHasher
{
    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
