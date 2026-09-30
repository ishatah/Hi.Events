<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Notification\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\NotificationChannel;

/**
 * A recipient and the addresses they can be reached on.
 *
 * Addresses are carried here rather than looked up per channel because most recipients are
 * not users — an attendee, a person, an order buyer and a staff member on a shift all live in
 * different tables with different column names, and the bus should not know about any of them.
 */
class NotificationRecipientDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $recipientType,
        public readonly int $recipientId,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $pushToken = null,
        public readonly ?string $locale = null,
    ) {}

    public function addressFor(NotificationChannel $channel): ?string
    {
        return match ($channel) {
            NotificationChannel::EMAIL => $this->email,
            NotificationChannel::SMS, NotificationChannel::WHATSAPP => $this->phone,
            NotificationChannel::PUSH => $this->pushToken,
            // An in-app message needs no address: the recipient id is the address, and it
            // is read when they next open the app.
            NotificationChannel::IN_APP => (string) $this->recipientId,
        };
    }

    /**
     * Preferences are held against the thing that has a will of its own. An order is a
     * transaction, so its buyer's choices are the person's.
     */
    public function subjectType(): string
    {
        return $this->recipientType === 'USER' ? 'USER' : 'PERSON';
    }
}
