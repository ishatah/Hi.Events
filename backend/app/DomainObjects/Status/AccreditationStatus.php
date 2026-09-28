<?php

namespace HiEvents\DomainObjects\Status;

enum AccreditationStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case WITHDRAWN = 'WITHDRAWN';
    case EXPIRED = 'EXPIRED';

    public function isDecided(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED, self::WITHDRAWN, self::EXPIRED], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, match ($this) {
            self::DRAFT => [self::SUBMITTED, self::WITHDRAWN],
            self::SUBMITTED => [self::UNDER_REVIEW, self::WITHDRAWN],
            self::UNDER_REVIEW => [self::APPROVED, self::REJECTED, self::SUBMITTED],
            self::APPROVED => [self::EXPIRED],
            self::REJECTED => [self::UNDER_REVIEW],
            self::WITHDRAWN, self::EXPIRED => [],
        }, true);
    }
}
