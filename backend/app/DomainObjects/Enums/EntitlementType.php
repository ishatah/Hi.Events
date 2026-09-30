<?php

namespace HiEvents\DomainObjects\Enums;

/**
 * What a sponsor is buying. Several carry system evidence rather than a screenshot, which
 * is the difference between a fulfilment report and a spreadsheet nobody trusts.
 */
enum EntitlementType: string
{
    use BaseEnum;

    case LOGO_PLACEMENT = 'LOGO_PLACEMENT';
    case GUEST_PASSES = 'GUEST_PASSES';
    case STAFF_PASSES = 'STAFF_PASSES';
    case BOOTH = 'BOOTH';
    case SPEAKING_SLOT = 'SPEAKING_SLOT';
    case BRANDED_ZONE = 'BRANDED_ZONE';
    case BRANDED_SESSION = 'BRANDED_SESSION';
    case EMAIL_MENTION = 'EMAIL_MENTION';
    case SOCIAL_POST = 'SOCIAL_POST';
    case OTHER = 'OTHER';

    /**
     * Whether the platform can prove delivery from its own append-only records, rather
     * than needing somebody to attach a screenshot and be believed.
     */
    public function hasSystemEvidence(): bool
    {
        return match ($this) {
            self::GUEST_PASSES, self::BOOTH, self::BRANDED_ZONE,
            self::BRANDED_SESSION, self::SPEAKING_SLOT => true,
            default => false,
        };
    }
}
