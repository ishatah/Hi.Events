<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Mfa;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Auth\MfaService;
use Illuminate\Http\JsonResponse;

class GetMfaStatusAction extends BaseAction
{
    public function __construct(
        private readonly MfaService $mfaService,
    ) {}

    public function __invoke(): JsonResponse
    {
        $userId = $this->getAuthenticatedUser()->getId();

        return $this->jsonResponse([
            'enabled' => $this->mfaService->isEnabled($userId),
            'required' => $this->mfaService->isRequiredFor($userId),
            'remaining_recovery_codes' => $this->mfaService->remainingRecoveryCodes($userId),
        ]);
    }
}
