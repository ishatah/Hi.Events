<?php

namespace HiEvents\DomainObjects\Enums;

/**
 * The outcome of an access decision.
 *
 * Denials are enumerated rather than collapsed into one value because a denied scan is
 * the most operationally interesting event: a spike in DENIED_NO_GRANT means a
 * misconfigured rule, whereas a spike in DENIED_ANTIPASSBACK means badge sharing.
 *
 * @see docs/arzo-master-plan/24-access-control.md
 */
enum AccessResult: string
{
    case GRANTED = 'GRANTED';
    case GRANTED_OVERRIDE = 'GRANTED_OVERRIDE';
    case DENIED_NO_CREDENTIAL = 'DENIED_NO_CREDENTIAL';
    case DENIED_NO_GRANT = 'DENIED_NO_GRANT';
    case DENIED_TIME_WINDOW = 'DENIED_TIME_WINDOW';
    case DENIED_CAPACITY = 'DENIED_CAPACITY';
    case DENIED_ANTIPASSBACK = 'DENIED_ANTIPASSBACK';
    case DENIED_MAX_ENTRIES = 'DENIED_MAX_ENTRIES';
    case DENIED_REVOKED = 'DENIED_REVOKED';
    case DENIED_RULE = 'DENIED_RULE';

    public function isGranted(): bool
    {
        return $this === self::GRANTED || $this === self::GRANTED_OVERRIDE;
    }
}
