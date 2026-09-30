<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Auth\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

/**
 * What the user is shown once during enrolment.
 *
 * The secret and the recovery codes appear here and nowhere else: both are shown at the moment
 * they are created and never retrievable afterwards, because a factor the platform can read
 * back on demand is not a second factor.
 */
class MfaEnrolmentDTO extends BaseDataObject
{
    /**
     * @param  array<int, string>  $recoveryCodes
     */
    public function __construct(
        public readonly string $secret,
        public readonly string $provisioningUri,
        public readonly array $recoveryCodes,
    ) {}
}
