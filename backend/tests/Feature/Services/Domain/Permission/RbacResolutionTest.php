<?php

namespace Tests\Feature\Services\Domain\Permission;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Enums\SystemRole;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Permission\EventRoleAssignmentService;
use HiEvents\Services\Domain\Permission\PermissionResolutionService;
use HiEvents\Services\Domain\Permission\RoleSeedService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RbacResolutionTest extends TestCase
{
    use DatabaseTransactions;

    private PermissionResolutionService $resolution;

    private EventRoleAssignmentService $assignment;

    private int $accountId;

    private int $userId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        app(RoleSeedService::class)->seedSystemRoles();

        $this->resolution = app(PermissionResolutionService::class);
        $this->assignment = app(EventRoleAssignmentService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent();
    }

    public function test_every_seeded_permission_exists_in_the_enum(): void
    {
        $db = DB::table('permissions')->pluck('name')->sort()->values()->all();
        $enum = collect(Permission::cases())->map(fn (Permission $p): string => $p->value)
            ->sort()->values()->all();

        $this->assertSame($enum, $db, 'The Permission enum and the permissions table must not drift.');
    }

    public function test_the_three_existing_roles_keep_unrestricted_account_access(): void
    {
        foreach ([Role::SUPERADMIN, Role::ADMIN, Role::ORGANIZER] as $role) {
            DB::table('account_users')
                ->where('user_id', $this->userId)
                ->where('account_id', $this->accountId)
                ->update(['role' => $role->name]);

            $permissions = $this->resolution->accountPermissions($this->userId, $this->accountId);

            $this->assertCount(
                count(Permission::cases()),
                $permissions,
                sprintf('Introducing RBAC must not narrow %s. It is behaviourally inert by design.', $role->name)
            );
        }
    }

    public function test_an_unknown_user_holds_nothing(): void
    {
        $this->assertSame([], $this->resolution->accountPermissions(99999999, $this->accountId));
        $this->assertFalse(
            $this->resolution->accountHolds(99999999, $this->accountId, Permission::EVENT_VIEW)
        );
    }

    public function test_a_checkin_operator_can_check_in_but_not_refund(): void
    {
        $operator = $this->makeAccountMember('CHECKIN_OPERATOR');

        $this->assertTrue(
            $this->resolution->accountHolds($operator, $this->accountId, Permission::ATTENDEE_CHECKIN)
        );
        $this->assertFalse(
            $this->resolution->accountHolds($operator, $this->accountId, Permission::ORDER_REFUND),
            'A door operator must not be able to move money.'
        );
    }

    public function test_a_per_event_grant_does_not_leak_to_another_event(): void
    {
        $member = $this->makeAccountMember('VIEWER');
        $otherEventId = $this->makeEvent();

        $this->assignment->grant(
            eventId: $this->eventId,
            userId: $member,
            roleName: SystemRole::CHECKIN_OPERATOR->value,
            accountId: $this->accountId,
        );

        $this->assertTrue(
            $this->resolution->eventHolds($member, $this->accountId, $this->eventId, Permission::ATTENDEE_CHECKIN)
        );
        $this->assertFalse(
            $this->resolution->eventHolds($member, $this->accountId, $otherEventId, Permission::ATTENDEE_CHECKIN),
            'A per-event grant must not apply to another event.'
        );
    }

    public function test_an_expired_per_event_grant_confers_nothing(): void
    {
        $member = $this->makeAccountMember('VIEWER');

        $this->assignment->grant(
            eventId: $this->eventId,
            userId: $member,
            roleName: SystemRole::CHECKIN_OPERATOR->value,
            accountId: $this->accountId,
        );

        DB::table('event_users')
            ->where('event_id', $this->eventId)
            ->where('user_id', $member)
            ->update(['expires_at' => now()->subMinute()]);

        $this->assertFalse(
            $this->resolution->eventHolds($member, $this->accountId, $this->eventId, Permission::ATTENDEE_CHECKIN),
            'Temporary staff must lose access when the grant expires.'
        );
    }

    public function test_an_account_level_role_cannot_be_granted_per_event(): void
    {
        $member = $this->makeAccountMember('VIEWER');

        $this->expectExceptionMessage('That role cannot be granted on a single event.');

        $this->assignment->grant(
            eventId: $this->eventId,
            userId: $member,
            roleName: SystemRole::ADMIN->value,
            accountId: $this->accountId,
        );
    }

    public function test_a_user_outside_the_account_cannot_be_granted_a_role(): void
    {
        $stranger = User::factory()->withAccount()->create();

        $this->expectExceptionMessage('That user is not a member of this account.');

        $this->assignment->grant(
            eventId: $this->eventId,
            userId: (int) $stranger->id,
            roleName: SystemRole::CHECKIN_OPERATOR->value,
            accountId: $this->accountId,
        );
    }

    public function test_granting_twice_updates_rather_than_duplicates(): void
    {
        $member = $this->makeAccountMember('VIEWER');

        $this->assignment->grant(
            eventId: $this->eventId,
            userId: $member,
            roleName: SystemRole::CHECKIN_OPERATOR->value,
            accountId: $this->accountId,
        );
        $this->assignment->grant(
            eventId: $this->eventId,
            userId: $member,
            roleName: SystemRole::BADGE_OPERATOR->value,
            accountId: $this->accountId,
        );

        $this->assertSame(
            1,
            DB::table('event_users')
                ->where('event_id', $this->eventId)
                ->where('user_id', $member)
                ->whereNull('deleted_at')
                ->count()
        );
        $this->assertTrue(
            $this->resolution->eventHolds($member, $this->accountId, $this->eventId, Permission::BADGE_PRINT)
        );
        $this->assertFalse(
            $this->resolution->eventHolds($member, $this->accountId, $this->eventId, Permission::ATTENDEE_CHECKIN),
            'Re-granting replaces the role rather than accumulating permissions.'
        );
    }

    public function test_revoking_removes_the_grant(): void
    {
        $member = $this->makeAccountMember('VIEWER');

        $this->assignment->grant(
            eventId: $this->eventId,
            userId: $member,
            roleName: SystemRole::CHECKIN_OPERATOR->value,
            accountId: $this->accountId,
        );
        $this->assignment->revoke($this->eventId, $member);

        $this->assertFalse(
            $this->resolution->eventHolds($member, $this->accountId, $this->eventId, Permission::ATTENDEE_CHECKIN)
        );
    }

    public function test_seeding_is_idempotent_and_reconciles(): void
    {
        $seedService = app(RoleSeedService::class);

        $before = DB::table('permission_role_permissions')->count();
        $seedService->seedSystemRoles();
        $this->assertSame($before, DB::table('permission_role_permissions')->count());

        $roleId = DB::table('permission_roles')->where('name', 'CHECKIN_OPERATOR')->value('id');
        $refundId = DB::table('permissions')->where('name', 'order.refund')->value('id');

        DB::table('permission_role_permissions')->insert([
            'permission_role_id' => $roleId,
            'permission_id' => $refundId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $seedService->seedSystemRoles();

        $this->assertFalse(
            DB::table('permission_role_permissions')
                ->where('permission_role_id', $roleId)
                ->where('permission_id', $refundId)
                ->exists(),
            'Reconciliation must revoke a permission removed from a role definition.'
        );
    }

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

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'RBAC Organizer',
            'email' => 'rbac-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'RBAC Test Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(10),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'DRAFT',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
