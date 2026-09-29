<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Device;

use Carbon\Carbon;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Device\DeviceFleetService;
use HiEvents\Services\Infrastructure\ApiKey\ApiPrincipalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatDeviceAction extends BaseAction
{
    public function __construct(
        private readonly DeviceFleetService $fleet,
        private readonly ApiPrincipalContext $principalContext,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $principal = $this->principalContext->get();

        if ($principal === null || ! $principal->isDevice()) {
            return $this->jsonResponse(['message' => __('This endpoint is for devices.')], 403);
        }

        $clock = $request->input('clock');

        $result = $this->fleet->heartbeat(
            deviceId: $principal->id,
            deviceClock: $clock !== null ? Carbon::parse((string) $clock) : null,
            batteryLevel: $request->input('battery_level') !== null
                ? (int) $request->input('battery_level')
                : null,
            appVersion: $request->input('app_version') !== null
                ? (string) $request->input('app_version')
                : null,
            syncCursor: $request->input('sync_cursor') !== null
                ? (string) $request->input('sync_cursor')
                : null,
        );

        return $this->jsonResponse($result);
    }
}
