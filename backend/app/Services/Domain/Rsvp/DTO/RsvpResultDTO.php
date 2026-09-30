<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Rsvp\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class RsvpResultDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $invitationId,
        public readonly int $responseId,
        public readonly string $response,
        public readonly int $partySize,
        public readonly ?string $changedFrom = null,
    ) {}

    public function isChange(): bool
    {
        return $this->changedFrom !== null && $this->changedFrom !== $this->response;
    }
}
