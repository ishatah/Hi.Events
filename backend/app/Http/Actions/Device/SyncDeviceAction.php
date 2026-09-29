<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Device;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Device\SyncDeviceRequest;
use HiEvents\Services\Domain\Device\DeviceSyncService;
use HiEvents\Services\Infrastructure\ApiKey\ApiPrincipalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * The device's own sync endpoint.
 *
 * Authenticated by the device's key rather than a user's JWT, and the device syncs itself —
 * the id comes from the resolved principal rather than the URL, so one tablet cannot pull
 * another's roster by changing a number.
 *
 * @see docs/arzo-master-plan/71-realtime-architecture.md
 */
class SyncDeviceAction extends BaseAction
{
    public function __construct(
        private readonly DeviceSyncService $syncService,
        private readonly ApiPrincipalContext $principalContext,
    ) {}

    public function __invoke(SyncDeviceRequest $request): JsonResponse
    {
        $principal = $this->principalContext->get();

        if ($principal === null || ! $principal->isDevice()) {
            return $this->jsonResponse(
                ['message' => __('This endpoint is for devices.')],
                403
            );
        }

        try {
            $result = $this->syncService->sync(
                deviceId: $principal->id,
                cursor: $request->validated('cursor'),
                pendingLogs: (array) ($request->validated('pending_logs') ?? []),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['device' => $exception->getMessage()]);
        }

        return $this->jsonResponse([
            'accepted_logs' => $result->acceptedLogs,
            'duplicate_logs' => $result->duplicateLogs,
            'rejected_logs' => $result->rejectedLogs,
            'credentials_delta' => $result->credentialsDelta,
            'grants_delta' => $result->grantsDelta,
            'deny_list' => $result->denyList,
            'config' => $result->config,
            'new_cursor' => $result->newCursor,
            'has_more' => $result->hasMore,
        ]);
    }
}
