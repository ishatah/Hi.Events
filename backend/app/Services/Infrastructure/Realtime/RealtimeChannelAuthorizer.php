<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Realtime;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Services\Domain\Permission\PermissionResolutionService;
use Illuminate\Database\DatabaseManager;

/**
 * Decides who may listen to an event's private channels.
 *
 * A channel is a read stream of live operational data — who just scanned in, which incident
 * was raised, which device dropped off. Subscribing to one is a read, so it is gated on the
 * same permissions the equivalent REST endpoint requires rather than on a separate
 * vocabulary that could drift from it.
 *
 * @see docs/arzo-master-plan/71-realtime-architecture.md
 */
class RealtimeChannelAuthorizer
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly PermissionResolutionService $permissionResolutionService,
    ) {}

    public function canListenToEvent(int $userId, int $eventId, Permission $permission): bool
    {
        $accountId = $this->accountForEvent($eventId);

        if ($accountId === null) {
            return false;
        }

        // Account membership is checked before the permission. A user who holds
        // attendee.view in their own account must not inherit it against somebody else's
        // event just because the permission name matches.
        if (! $this->isMemberOfAccount($userId, $accountId)) {
            return false;
        }

        return $this->permissionResolutionService->eventHolds($userId, $accountId, $eventId, $permission);
    }

    /**
     * Channel names and the permission each requires.
     *
     * Kept in one place so a new channel cannot be added without deciding who may hear it.
     *
     * @return array<string, Permission>
     */
    public static function channelPermissions(): array
    {
        return [
            'access' => Permission::ACCESS_LOGS_VIEW,
            'checkin' => Permission::ATTENDEE_CHECKIN,
            'incidents' => Permission::INCIDENT_MANAGE,
            'devices' => Permission::DEVICE_MANAGE,
            'occupancy' => Permission::ACCESS_LOGS_VIEW,
        ];
    }

    private function accountForEvent(int $eventId): ?int
    {
        $accountId = $this->databaseManager->table('events')
            ->where('id', $eventId)
            ->whereNull('deleted_at')
            ->value('account_id');

        return $accountId !== null ? (int) $accountId : null;
    }

    private function isMemberOfAccount(int $userId, int $accountId): bool
    {
        return $this->databaseManager->table('account_users')
            ->where('user_id', $userId)
            ->where('account_id', $accountId)
            ->where('status', 'ACTIVE')
            ->exists();
    }
}
