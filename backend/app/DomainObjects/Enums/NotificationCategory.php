<?php

namespace HiEvents\DomainObjects\Enums;

/**
 * What a notification is for, which decides its channels, whether quiet hours apply and
 * whether the recipient may switch it off.
 */
enum NotificationCategory: string
{
    use BaseEnum;

    case CRITICAL_OPERATIONAL = 'CRITICAL_OPERATIONAL';
    case OPERATIONAL = 'OPERATIONAL';
    case REMINDER = 'REMINDER';
    case ACCOUNT = 'ACCOUNT';
    case STAFF_ALERT = 'STAFF_ALERT';

    /**
     * A gate change at 23:00 is exactly the message somebody needs overnight, and an auth
     * code held until morning is useless.
     */
    public function bypassesQuietHours(): bool
    {
        return match ($this) {
            self::CRITICAL_OPERATIONAL, self::ACCOUNT, self::STAFF_ALERT => true,
            self::OPERATIONAL, self::REMINDER => false,
        };
    }

    /**
     * Two floors: a critical operational message cannot be switched off entirely, only its
     * channels chosen, and an account message cannot be switched off at all — an auth code
     * does not change channel.
     */
    public function canBeDisabled(): bool
    {
        return $this !== self::ACCOUNT && $this !== self::CRITICAL_OPERATIONAL;
    }

    /**
     * The channels to try, in order. Fallback never repeats a channel.
     *
     * @return array<int, NotificationChannel>
     */
    public function channelOrder(): array
    {
        return match ($this) {
            self::CRITICAL_OPERATIONAL => [
                NotificationChannel::PUSH,
                NotificationChannel::WHATSAPP,
                NotificationChannel::SMS,
                NotificationChannel::EMAIL,
            ],
            self::OPERATIONAL => [NotificationChannel::EMAIL, NotificationChannel::IN_APP],
            self::REMINDER => [NotificationChannel::PUSH, NotificationChannel::IN_APP],
            self::ACCOUNT => [NotificationChannel::EMAIL],
            self::STAFF_ALERT => [NotificationChannel::PUSH, NotificationChannel::SMS],
        };
    }

    public function allowsFallback(): bool
    {
        return match ($this) {
            // An auth code that arrives by a second route is a security problem, not a
            // convenience, and a reminder is not worth spending money on.
            self::ACCOUNT, self::REMINDER => false,
            default => true,
        };
    }
}
