<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Device;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Device\DeviceEnrolmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Exchanges a pairing code for the device's key.
 *
 * Unauthenticated by necessity — the device has no credential yet, which is the whole point
 * of pairing. The code is the credential: short, single-use and expiring, and rate limited
 * on the route so it cannot be brute-forced.
 *
 * @see docs/arzo-master-plan/40-device-management.md
 */
class PairDeviceAction extends BaseAction
{
    public function __construct(
        private readonly DeviceEnrolmentService $enrolment,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $code = (string) ($request->input('pairing_code') ?? '');

        if ($code === '') {
            throw ValidationException::withMessages(['pairing_code' => __('A pairing code is required.')]);
        }

        try {
            $paired = $this->enrolment->pair($code);
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['pairing_code' => $exception->getMessage()]);
        }

        // Returned once. There is no endpoint that gives it back.
        return $this->jsonResponse([
            'device_id' => $paired['device_id'],
            'key' => $paired['key'],
            'message' => __('Store this key now. It cannot be retrieved again.'),
        ]);
    }
}
