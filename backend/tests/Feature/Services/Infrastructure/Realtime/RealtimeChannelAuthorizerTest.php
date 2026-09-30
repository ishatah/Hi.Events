<?php

namespace Tests\Feature\Services\Infrastructure\Realtime;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Events\Realtime\AccessScanRecorded;
use HiEvents\Events\Realtime\DeviceStateChanged;
use HiEvents\Events\Realtime\IncidentRaised;
use HiEvents\Events\Realtime\ZoneOccupancyChanged;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Permission\RoleSeedService;
use HiEvents\Services\Infrastructure\Realtime\RealtimeChannelAuthorizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RealtimeChannelAuthorizerTest extends TestCase
{
    use DatabaseTransactions;

    private RealtimeChannelAuthorizer $authorizer;

    private int $accountId;

    private int $userId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        app(RoleSeedService::class)->seedSystemRoles();

        $this->authorizer = app(RealtimeChannelAuthorizer::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent($this->accountId, $this->userId);
    }

    public function test_an_admin_may_listen_to_their_own_events_channels(): void
    {
        foreach (RealtimeChannelAuthorizer::channelPermissions() as $permission) {
            $this->assertTrue(
                $this->authorizer->canListenToEvent($this->userId, $this->eventId, $permission),
                sprintf('An admin should hear %s.', $permission->value)
            );
        }
    }

    public function test_a_user_from_another_account_may_not_listen(): void
    {
        $stranger = User::factory()->withAccount()->create();

        $this->assertFalse(
            $this->authorizer->canListenToEvent(
                (int) $stranger->id,
                $this->eventId,
                Permission::ACCESS_LOGS_VIEW
            ),
            'Holding a permission in your own account must not carry into somebody else\'s event.'
        );
    }

    public function test_a_checkin_operator_hears_checkin_but_not_incidents(): void
    {
        $operator = $this->makeAccountMember('CHECKIN_OPERATOR');

        $this->assertTrue(
            $this->authorizer->canListenToEvent($operator, $this->eventId, Permission::ATTENDEE_CHECKIN)
        );
        $this->assertFalse(
            $this->authorizer->canListenToEvent($operator, $this->eventId, Permission::INCIDENT_MANAGE),
            'A door operator has no reason to watch the incident stream.'
        );
    }

    public function test_a_deactivated_member_may_not_listen(): void
    {
        $member = $this->makeAccountMember('ADMIN');

        DB::table('account_users')
            ->where('user_id', $member)
            ->where('account_id', $this->accountId)
            ->update(['status' => 'INACTIVE']);

        $this->assertFalse(
            $this->authorizer->canListenToEvent($member, $this->eventId, Permission::ACCESS_LOGS_VIEW),
            'Deactivating somebody must close their live streams too, not just their REST access.'
        );
    }

    public function test_an_unknown_event_is_refused(): void
    {
        $this->assertFalse(
            $this->authorizer->canListenToEvent($this->userId, 99999999, Permission::ACCESS_LOGS_VIEW)
        );
    }

    public function test_a_soft_deleted_event_is_refused(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['deleted_at' => now()]);

        $this->assertFalse(
            $this->authorizer->canListenToEvent($this->userId, $this->eventId, Permission::ACCESS_LOGS_VIEW)
        );
    }

    public function test_every_channel_declares_a_permission(): void
    {
        $channels = RealtimeChannelAuthorizer::channelPermissions();

        $this->assertNotEmpty($channels);

        foreach ($channels as $name => $permission) {
            $this->assertIsString($name);
            $this->assertInstanceOf(
                Permission::class,
                $permission,
                'A channel without a declared permission would be readable by anyone.'
            );
        }
    }

    // ---------------------------------------------------------------- payloads

    public function test_an_access_scan_broadcast_carries_no_personal_data(): void
    {
        $payload = (new AccessScanRecorded(
            eventId: $this->eventId,
            zoneId: 5,
            accessPointId: 9,
            result: 'GRANTED',
            granted: true,
            occurredAt: now()->toIso8601String(),
        ))->broadcastWith();

        $this->assertSame(
            ['zone_id', 'access_point_id', 'result', 'granted', 'occurred_at'],
            array_keys($payload),
            'A live board needs the verdict and the place, not the holder — this goes to '
            .'several operators\' screens at once.'
        );
    }

    public function test_an_access_scan_broadcasts_on_its_events_channel(): void
    {
        $channels = (new AccessScanRecorded($this->eventId, 1, 1, 'GRANTED', true, now()->toIso8601String()))
            ->broadcastOn();

        $this->assertSame(
            'private-event.'.$this->eventId.'.access',
            $channels[0]->name,
            'One event must not broadcast onto another event\'s channel.'
        );
    }

    public function test_an_incident_broadcast_carries_its_reference(): void
    {
        $payload = (new IncidentRaised($this->eventId, 7, '12', 'Barrier down', 'SEV2', 'OPEN', 3))
            ->broadcastWith();

        $this->assertSame('12', $payload['reference']);
        $this->assertSame('SEV2', $payload['severity']);
    }

    public function test_a_device_state_broadcast_names_the_device(): void
    {
        $payload = (new DeviceStateChanged($this->eventId, 4, 'Gate 3 Scanner', 'CLOCK_SKEW', 'off by 600s'))
            ->broadcastWith();

        $this->assertSame('Gate 3 Scanner', $payload['name']);
        $this->assertSame('CLOCK_SKEW', $payload['state']);
    }

    public function test_occupancy_broadcasts_utilisation_only_when_capacity_is_known(): void
    {
        $withCapacity = (new ZoneOccupancyChanged($this->eventId, 1, 50, 200))->broadcastWith();
        $withoutCapacity = (new ZoneOccupancyChanged($this->eventId, 1, 50, null))->broadcastWith();

        $this->assertSame(0.25, $withCapacity['utilisation']);
        $this->assertNull(
            $withoutCapacity['utilisation'],
            'A zone with no limit has no utilisation; zero would read as empty.'
        );
    }

    public function test_occupancy_does_not_divide_by_zero(): void
    {
        $payload = (new ZoneOccupancyChanged($this->eventId, 1, 10, 0))->broadcastWith();

        $this->assertNull($payload['utilisation']);
    }

    // ---------------------------------------------------------------- fixtures

    private function makeAccountMember(string $role): int
    {
        $user = User::factory()->create();

        DB::table('account_users')->insert([
            'user_id' => $user->id,
            'account_id' => $this->accountId,
            'role' => $role,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) $user->id;
    }

    private function makeEvent(int $accountId, int $userId): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Realtime Organizer',
            'email' => 'rt-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Realtime Event',
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'start_date' => now()->addDays(3),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
