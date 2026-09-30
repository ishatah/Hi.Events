<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Request\Auth\LoginRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Auth\DTO\LoginCredentialsDTO;
use HiEvents\Services\Application\Handlers\Auth\LoginHandler;
use Illuminate\Http\JsonResponse;

class LoginAction extends BaseAuthAction
{
    private LoginHandler $loginHandler;

    public function __construct(LoginHandler $loginHandler)
    {
        $this->loginHandler = $loginHandler;
    }

    public function __invoke(LoginRequest $request): JsonResponse
    {
        try {
            $loginResponse = $this->loginHandler->handle(new LoginCredentialsDTO(
                email: strtolower($request->validated('email')),
                password: $request->validated('password'),
                accountId: (int) $request->validated('account_id'),
                mfaCode: $request->input('mfa_code'),
            ));
        } catch (UnauthorizedException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            );
        }

        // No token yet: the password was right but a second factor is owed. Deliberately
        // says only that, and nothing about the account or the user — a challenge response is
        // reachable with a guessed password, so it must not confirm anything more than the
        // token-bearing response already would.
        if ($loginResponse->mfaRequired) {
            return $this->jsonResponse(['mfa_required' => true], statusCode: ResponseCodes::HTTP_ACCEPTED);
        }

        if ($loginResponse->mfaEnrolmentRequired) {
            return $this->jsonResponse(
                ['mfa_enrolment_required' => true],
                statusCode: ResponseCodes::HTTP_ACCEPTED
            );
        }

        return $this->respondWithToken($loginResponse->token, $loginResponse->accounts);
    }
}
