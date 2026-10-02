<?php

namespace HiEvents\Services\Domain\Auth;

use Carbon\Carbon;
use HiEvents\DomainObjects\PasswordResetTokenDomainObject;
use HiEvents\Exceptions\InvalidPasswordResetTokenException;
use HiEvents\Repository\Interfaces\PasswordResetTokenRepositoryInterface;
use HiEvents\Services\Infrastructure\TokenGenerator\EmailedTokenHasher;
use Illuminate\Config\Repository;

class ResetPasswordTokenValidateService
{
    private PasswordResetTokenRepositoryInterface $passwordResetTokenRepository;

    private Repository $config;

    private EmailedTokenHasher $emailedTokenHasher;

    public function __construct(
        PasswordResetTokenRepositoryInterface $passwordResetTokenRepository,
        Repository $config,
        EmailedTokenHasher $emailedTokenHasher
    ) {
        $this->passwordResetTokenRepository = $passwordResetTokenRepository;
        $this->config = $config;
        $this->emailedTokenHasher = $emailedTokenHasher;
    }

    /**
     * @throws InvalidPasswordResetTokenException
     */
    public function validateAndFetchToken(string $token): PasswordResetTokenDomainObject
    {
        $resetToken = $this->passwordResetTokenRepository->findFirstWhere([
            'token' => $this->emailedTokenHasher->hash($token),
        ]);
        if (! $resetToken) {
            throw new InvalidPasswordResetTokenException(__('Invalid reset token'));
        }

        if ($this->isTokenExpired($resetToken->getCreatedAt())) {
            throw new InvalidPasswordResetTokenException(__('Reset token has expired'));
        }

        return $resetToken;
    }

    private function isTokenExpired(string $createdAt): bool
    {
        return (new Carbon($createdAt))
            ->addMinutes(
                $this->config->get('app.reset_password_token_expiry_in_min')
            )->isPast();
    }
}
