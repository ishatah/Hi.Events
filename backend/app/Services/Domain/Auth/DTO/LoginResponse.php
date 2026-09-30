<?php

namespace HiEvents\Services\Domain\Auth\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\UserDomainObject;
use Illuminate\Support\Collection;

class LoginResponse extends BaseDTO
{
    public function __construct(
        public Collection $accounts,
        public readonly ?string $token,
        public readonly UserDomainObject $user,
        public readonly ?int $accountId = null,

        // Set when the password was right but a second factor is still owed. The token stays
        // null, so nothing downstream can mistake a half-finished login for a finished one.
        public readonly bool $mfaRequired = false,
        public readonly bool $mfaEnrolmentRequired = false,
    ) {}
}
