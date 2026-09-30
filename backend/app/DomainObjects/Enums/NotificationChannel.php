<?php

namespace HiEvents\DomainObjects\Enums;

enum NotificationChannel: string
{
    use BaseEnum;

    case EMAIL = 'EMAIL';
    case SMS = 'SMS';
    case WHATSAPP = 'WHATSAPP';
    case PUSH = 'PUSH';
    case IN_APP = 'IN_APP';

    /**
     * Whether sending one costs money per message. A paid, interrupting channel is never
     * sent twice and never silently skipped when a budget runs out.
     */
    public function isMetered(): bool
    {
        return $this === self::SMS || $this === self::WHATSAPP;
    }

    public function usesPhoneNumber(): bool
    {
        return $this === self::SMS || $this === self::WHATSAPP;
    }
}
