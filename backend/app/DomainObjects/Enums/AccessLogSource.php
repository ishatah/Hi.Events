<?php

namespace HiEvents\DomainObjects\Enums;

/**
 * How a scan reached the server.
 *
 * The distinction is load-bearing for reconciliation: a record that arrived through a device's
 * sync batch was decided offline by that device, and the server may now disagree with it. One
 * that arrived live was decided by the server in the first place, so there is nothing to
 * reconcile.
 */
enum AccessLogSource: string
{
    use BaseEnum;

    case SCAN = 'SCAN';
    case OFFLINE_SYNC = 'OFFLINE_SYNC';

    /**
     * Whether the device, not the server, made this decision.
     *
     * Declared by the caller rather than inferred from how old the record looks. The previous
     * heuristic — a client id plus a timestamp over two minutes old — flagged a slow online
     * retry as offline and missed a fast offline replay entirely, so reconciliation was
     * reading a field that did not mean what it said.
     */
    public function isOfflineReplay(): bool
    {
        return $this === self::OFFLINE_SYNC;
    }
}
