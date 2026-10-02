<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum EventStatus
{
    use BaseEnum;

    case DRAFT;
    case LIVE;
    case ARCHIVED;
    case PENDING_MANUAL_REVIEW;

    /**
     * Archiving is the end of an event's life, so an archived event is never put back on
     * sale; it is duplicated instead. PENDING_MANUAL_REVIEW is reached by the spam check
     * rather than by an organizer, so it is not a destination an organizer may choose.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        return match ($this) {
            self::DRAFT => $target === self::LIVE || $target === self::ARCHIVED,
            self::LIVE => $target === self::DRAFT || $target === self::ARCHIVED,
            self::ARCHIVED, self::PENDING_MANUAL_REVIEW => false,
        };
    }
}
