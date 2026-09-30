<?php

namespace HiEvents\Services\Domain\Auth;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Services\Domain\Auth\DTO\LoginResponse;
use Illuminate\Support\Collection;
use PHPOpenSourceSaver\JWTAuth\JWTAuth;
use Psr\Log\LoggerInterface;

readonly class LoginService
{
    public function __construct(
        private JWTAuth $jwtAuth,
        private LoggerInterface $logger,
        private AccountUserRepositoryInterface $accountUserRepository,
        private MfaService $mfaService,
    ) {}

    /**
     * @throws UnauthorizedException
     */
    public function authenticate(
        string $email,
        string $password,
        ?int $requestedAccountId,
        ?string $mfaCode = null,
    ): LoginResponse {
        // todo - refactor this so we don't have to call the jwtAuth twice
        $token = $this->jwtAuth->attempt([
            'email' => strtolower($email),
            'password' => $password,
        ]);

        if (! $token) {
            throw new UnauthorizedException(__('Username or Password are incorrect'));
        }

        /** @var UserDomainObject $user */
        $user = UserDomainObject::hydrateFromModel($this->jwtAuth->user());

        $userAccounts = $this->accountUserRepository
            ->loadRelation(new Relationship(domainObject: AccountDomainObject::class, name: 'account'))
            ->findWhere([
                'user_id' => $user->getId(),
            ]);

        $accounts = $userAccounts->map(fn ($accountUser) => $accountUser->getAccount());

        $accountId = $this->getAccountId($accounts, $requestedAccountId);

        if ($accountId) {
            $this->validateUserStatus($accountId, $userAccounts);
        }

        $userRole = $this->getUserRole($accountId, $userAccounts);

        // The password was right, but a second factor may still be owed. Interposed here
        // rather than after the token is minted, because a token issued first is a completed
        // login whatever the client does with the challenge afterwards.
        $userId = $user->getId();

        if ($this->mfaService->isEnabled($userId)) {
            if ($mfaCode === null) {
                return new LoginResponse(
                    accounts: $accounts,
                    token: null,
                    user: $user,
                    accountId: $accountId,
                    mfaRequired: true,
                );
            }

            if (! $this->mfaService->verify($userId, $mfaCode)) {
                throw new UnauthorizedException(__('That two-factor code is not valid'));
            }
        } elseif ($this->mfaService->isRequiredFor($userId)) {
            // An account insists on a factor this user has not set up. Refusing the login
            // outright would lock them out with no way back, so they are sent to enrol —
            // still with no token, so nothing else is reachable until they do.
            return new LoginResponse(
                accounts: $accounts,
                token: null,
                user: $user,
                accountId: $accountId,
                mfaEnrolmentRequired: true,
            );
        }

        return new LoginResponse(
            accounts: $accounts,
            token: $this->getToken(
                accounts: $accounts,
                email: $email,
                password: $password,
                requestedAccountId: $requestedAccountId,
                userRole: $userRole,
            ),
            user: $user,
            accountId: $accountId,
        );
    }

    private function getAccountId(Collection $accounts, ?int $requestedAccountId): ?int
    {
        if ($accounts->count() === 1) {
            return $accounts->first()->getId();
        }

        if ($requestedAccountId) {
            $verifiedAccount = $accounts->firstWhere(fn (AccountDomainObject $account) => $account->getId() === $requestedAccountId);

            if ($verifiedAccount === null) {
                throw new UnauthorizedException(__('Account not found'));
            }

            return $verifiedAccount->getId();
        }

        return null;
    }

    private function getToken(
        Collection $accounts,
        string $email,
        string $password,
        ?int $requestedAccountId,
        ?Role $userRole,
    ): ?string {
        $accountId = $this->getAccountId($accounts, $requestedAccountId);

        // if there's no account, we can't generate a token. The user will be prompted to select an account
        if ($accountId === null) {
            return null;
        }

        $claims = ['account_id' => $accountId];

        if ($userRole !== null) {
            $claims['role'] = $userRole->value;
        }

        $token = $this->jwtAuth->claims($claims)->attempt([
            'email' => strtolower($email),
            'password' => $password,
        ]);

        if (! $token) {
            throw new UnauthorizedException(__('Username or Password are incorrect'));
        }

        return $token;
    }

    private function validateUserStatus(int $accountId, Collection $userAccounts): void
    {
        /** @var AccountUserDomainObject $currentAccount */
        $currentAccount = $userAccounts
            ->first(fn (AccountUserDomainObject $userAccount) => $userAccount->getAccountId() === $accountId);

        if ($currentAccount->getStatus() !== UserStatus::ACTIVE->name) {
            $this->logger->info(__('Attempt to log in to a non-active account'), $currentAccount->toArray());

            throw new UnauthorizedException(__('User account is not active'));
        }
    }

    private function getUserRole(?int $accountId, Collection $userAccounts): ?Role
    {
        if ($accountId === null) {
            return null;
        }

        /** @var AccountUserDomainObject $currentAccount */
        $currentAccount = $userAccounts
            ->first(fn (AccountUserDomainObject $userAccount) => $userAccount->getAccountId() === $accountId);

        return Role::from($currentAccount?->getRole());
    }
}
