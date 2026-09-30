<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Mfa;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Mfa\ConfirmMfaRequest;
use HiEvents\Services\Domain\Auth\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DisableMfaAction extends BaseAction
{
    public function __construct(
        private readonly MfaService $mfaService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(ConfirmMfaRequest $request): JsonResponse
    {
        try {
            // A code is required to turn it off: otherwise a stolen session removes the
            // factor that session was supposed to be protected by.
            $this->mfaService->disable(
                userId: $this->getAuthenticatedUser()->getId(),
                code: $request->validated('code'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['disabled' => true]);
    }
}
