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

    /**
     * Whether the channel reports that a human actually read it. Email opens are not
     * tracked, and web push has no delivery receipt at all.
     */
    public function reportsRead(): bool
    {
        return $this === self::WHATSAPP || $this === self::PUSH || $this === self::IN_APP;
    }

    public function usesPhoneNumber(): bool
    {
        return $this === self::SMS || $this === self::WHATSAPP;
    }
}
