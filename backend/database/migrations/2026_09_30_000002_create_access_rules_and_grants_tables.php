<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The access control rules engine.
     *
     * access_rules are declarative predicates evaluated in priority order. access_grants
     * are materialised from them when a credential is issued, so a door scan never has to
     * evaluate the whole rule set and an offline device can carry its grants locally.
     *
     * @see docs/arzo-master-plan/24-access-control.md
     */
    public function up(): void
    {
        Schema::create('access_rules', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 255);
            $table->integer('priority')->default(100);

            // DENY at a higher priority than ALLOW gives explicit blacklisting - a revoked
            // badge or a banned attendee - without deleting grants.
            $table->string('effect', 8)->default('ALLOW');

            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id')->nullable();

            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->jsonb('days_of_week')->nullable();
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();

            $table->unsignedInteger('max_entries')->nullable();
            $table->boolean('allow_reentry')->default(true);
            $table->unsignedInteger('min_reentry_seconds')->nullable();
            $table->boolean('enforce_capacity')->default(false);
            $table->boolean('requires_escort')->default(false);

            // Narrow escape hatch for predicates not worth first-class columns. Anything
            // used by more than two clients gets promoted to a column, or this becomes an
            // unqueryable, untestable DSL.
            $table->jsonb('conditions')->nullable();

            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'is_active', 'priority']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['target_type', 'target_id']);
        });

        DB::statement("
            ALTER TABLE access_rules
            ADD CONSTRAINT access_rules_effect_valid
            CHECK (effect IN ('ALLOW', 'DENY'))
        ");

        Schema::create('access_grants', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('sessions')->cascadeOnDelete();
            $table->foreignId('access_point_id')->nullable()->constrained('access_points')->cascadeOnDelete();
            $table->foreignId('source_rule_id')->nullable()->constrained('access_rules')->nullOnDelete();

            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->jsonb('days_of_week')->nullable();
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();

            $table->unsignedInteger('max_entries')->nullable();

            // Advisory only. Counters drift under offline replay, so authoritative
            // enforcement of max_entries counts access_logs rows instead.
            $table->unsignedInteger('entries_used')->default(0);

            $table->boolean('allow_reentry')->default(true);
            $table->unsignedInteger('min_reentry_seconds')->nullable();

            $table->string('status', 16)->default('ACTIVE');
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revocation_reason', 255)->nullable();

            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['credential_id', 'status']);
            $table->index(['zone_id', 'status']);
            $table->index(['session_id', 'status']);
        });

        // A grant must point at exactly one target, or the decision is ambiguous.
        DB::statement('
            ALTER TABLE access_grants
            ADD CONSTRAINT access_grants_exactly_one_target
            CHECK (
                (CASE WHEN zone_id IS NOT NULL THEN 1 ELSE 0 END)
              + (CASE WHEN room_id IS NOT NULL THEN 1 ELSE 0 END)
              + (CASE WHEN session_id IS NOT NULL THEN 1 ELSE 0 END)
              + (CASE WHEN access_point_id IS NOT NULL THEN 1 ELSE 0 END)
              = 1
            )
        ');

        Schema::create('zone_occupancy_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedInteger('occupancy');
            $table->unsignedInteger('capacity')->nullable();
            $table->timestampTz('captured_at')->useCurrent();

            $table->index(['zone_id', 'captured_at']);
            $table->index(['event_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_occupancy_snapshots');
        Schema::dropIfExists('access_grants');
        Schema::dropIfExists('access_rules');
    }
};
