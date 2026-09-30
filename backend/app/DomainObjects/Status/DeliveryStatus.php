<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

/**
 * SENT means the provider accepted it. DELIVERED means the provider reported delivery — for
 * email, that the receiving server accepted it, which is the most email can prove. READ
 * exists only where the channel reports it.
 */
enum DeliveryStatus: string
{
    use BaseEnum;

    case PENDING = 'PENDING';
    case SUPPRESSED = 'SUPPRESSED';
    case DEFERRED = 'DEFERRED';
    case SENDING = 'SENDING';
    case SENT = 'SENT';
    case DELIVERED = 'DELIVERED';
    case READ = 'READ';
    case FAILED = 'FAILED';
    case BOUNCED = 'BOUNCED';
    case EXPIRED = 'EXPIRED';
    case UNKNOWN = 'UNKNOWN';

    /**
     * Whether the message reached the recipient as far as the channel can tell. UNKNOWN is
     * deliberately excluded: it means a paid send may or may not have happened, and treating
     * that as success would hide it.
     */
    public function reachedRecipient(): bool
    {
        return match ($this) {
            self::SENT, self::DELIVERED, self::READ => true,
            default => false,
        };
    }

    public function warrantsFallback(): bool
    {
        return match ($this) {
            self::FAILED, self::BOUNCED => true,
            default => false,
        };
    }
}
