<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Capability-based permissions, replacing a three-value role enum whose default
     * level (ORGANIZER) checks nothing.
     *
     * This migration only creates the tables and seeds permissions. It changes no
     * authorization behaviour: nothing reads these yet. Migrating the 159 imperative
     * call sites is separate work, gated by the architecture and cross-tenant tests.
     *
     * @see docs/arzo-master-plan/09-permissions-and-roles.md
     */
    private const PERMISSIONS = [
        'event.view', 'event.create', 'event.update', 'event.publish', 'event.delete',
        'product.view', 'product.manage',
        'order.view', 'order.refund', 'order.export',
        'attendee.view', 'attendee.checkin', 'attendee.edit', 'attendee.export',
        'accreditation.view', 'accreditation.approve', 'accreditation.reject',
        'credential.issue', 'credential.revoke',
        'badge.print', 'badge.reprint', 'badge.void',
        'access.override', 'access.logs.view',
        'zone.manage', 'venue.manage',
        'session.manage', 'speaker.manage',
        'exhibitor.manage', 'lead.view',
        'device.manage', 'device.submit_scan',
        'report.view', 'report.export',
        'staff.manage', 'incident.manage',
        'account.manage', 'user.manage', 'apikey.manage',
        'webhook.manage', 'promo_code.manage', 'question.manage', 'message.send',
    ];

    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('permission_roles', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
            $table->string('name', 64);
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
        });

        DB::statement('
            CREATE UNIQUE INDEX permission_roles_name_unique
            ON permission_roles (COALESCE(account_id, 0), name)
            WHERE deleted_at IS NULL
        ');

        Schema::create('permission_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_role_id')->constrained('permission_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['permission_role_id', 'permission_id'], 'permission_role_permission_unique');
            $table->index('permission_id');
        });

        Schema::create('event_users', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_role_id')->constrained('permission_roles')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('granted_at')->useCurrent();

            // Temporary event staff should lose access automatically rather than when
            // somebody remembers to revoke it.
            $table->timestampTz('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
            $table->index('user_id');
        });

        DB::statement('
            CREATE UNIQUE INDEX event_users_unique
            ON event_users (event_id, user_id)
            WHERE deleted_at IS NULL
        ');

        $now = now();

        DB::table('permissions')->insert(array_map(
            static fn (string $name): array => [
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            self::PERMISSIONS
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('event_users');
        Schema::dropIfExists('permission_role_permissions');
        Schema::dropIfExists('permission_roles');
        Schema::dropIfExists('permissions');
    }
};
