<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Mfa;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Mfa\ConfirmMfaRequest;
use HiEvents\Services\Domain\Auth\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ConfirmMfaEnrolmentAction extends BaseAction
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
            $confirmed = $this->mfaService->confirmEnrolment(
                userId: $this->getAuthenticatedUser()->getId(),
                code: $request->validated('code'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        // Shown once. A recovery code the platform can hand back later is not a recovery code.
        return $this->jsonResponse(['recovery_codes' => $confirmed->recoveryCodes]);
    }
}
