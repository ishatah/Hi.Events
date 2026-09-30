<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Mfa;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Auth\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Starts two-factor enrolment for the calling user.
 *
 * Scoped to the caller and takes no user id: one that did would let somebody enrol a factor on
 * another person's account, which is a lockout rather than a login.
 */
class BeginMfaEnrolmentAction extends BaseAction
{
    public function __construct(
        private readonly MfaService $mfaService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(): JsonResponse
    {
        try {
            $enrolment = $this->mfaService->beginEnrolment(
                userId: $this->getAuthenticatedUser()->getId(),
                issuer: (string) config('app.name', 'ARZO'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['mfa' => $exception->getMessage()]);
        }

        return $this->jsonResponse([
            'secret' => $enrolment->secret,
            'provisioning_uri' => $enrolment->provisioningUri,
        ]);
    }
}
