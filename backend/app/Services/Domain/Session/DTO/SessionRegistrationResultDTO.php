<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Session\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\SessionRegistrationDomainObject;

class SessionRegistrationResultDTO extends BaseDataObject
{
    public function __construct(
        public readonly ?SessionRegistrationDomainObject $registration,
        public readonly bool $waitlisted,
        public readonly ?int $waitlistPosition = null,
    ) {}
}
