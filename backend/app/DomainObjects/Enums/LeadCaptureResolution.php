<?php

namespace HiEvents\DomainObjects\Enums;

enum LeadCaptureResolution: string
{
    use BaseEnum;

    case RESOLVED = 'RESOLVED';

    /**
     * The scan happened and is counted for booth traffic, but no personal data was
     * transferred because the attendee did not consent to sharing it.
     */
    case NO_CONSENT = 'NO_CONSENT';

    case UNKNOWN_IDENTIFIER = 'UNKNOWN_IDENTIFIER';

    case OTHER_EVENT = 'OTHER_EVENT';

    public function transferredData(): bool
    {
        return $this === self::RESOLVED;
    }
}
