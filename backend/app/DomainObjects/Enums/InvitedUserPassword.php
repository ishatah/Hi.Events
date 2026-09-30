<?php

namespace HiEvents\DomainObjects\Enums;

/**
 * The sentinel stored in `users.password` for a user who has been invited but has not yet set
 * one.
 *
 * A shared constant rather than a literal in each caller, because two paths depend on telling
 * "invited, no password yet" apart from "has a password": creation writes it, and invitation
 * acceptance reads it to decide whether it may set credentials at all. Users are global across
 * accounts, so getting that wrong lets an invitation to a second account overwrite the password
 * on the first.
 *
 * Not a valid bcrypt hash, so it can never authenticate.
 *
 * @see docs/arzo-master-plan/101-enterprise.md
 */
final class InvitedUserPassword
{
    public const SENTINEL = 'invited';

    public static function isUnset(?string $password): bool
    {
        return $password === null || $password === '' || $password === self::SENTINEL;
    }
}
