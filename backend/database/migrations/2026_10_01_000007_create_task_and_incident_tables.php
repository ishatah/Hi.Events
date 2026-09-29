<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @see docs/arzo-master-plan/58-task-management.md
     * @see docs/arzo-master-plan/60-incident-management.md
     */
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('event_category')->nullable();
            $table->jsonb('applies_to')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
        });

        Schema::create('task_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_template_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();

            // Relative to a point on the event timeline rather than an absolute date, so
            // instantiating a template against an event produces real times and moving the
            // event moves its deadlines with it.
            $table->string('anchor', 32);
            $table->integer('offset_minutes')->default(0);

            $table->string('owner_role', 64)->nullable();
            $table->string('check_key', 64)->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('is_blocking')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('event_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_template_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('TODO');
            $table->boolean('is_blocking')->default(false);
            $table->string('check_key', 64)->nullable();
            $table->jsonb('last_check_result')->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->boolean('requires_evidence')->default(false);

            // Lets a task point at the thing it concerns, so "Gate 3 has no device" links
            // to Gate 3 rather than describing it in prose.
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('waived_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status', 'due_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 32);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 32);
            $table->string('severity', 8);
            $table->string('status', 24)->default('OPEN');
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('occurred_at');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status', 'severity']);
        });

        // A human-quotable reference per event, so radio traffic can say "incident 12"
        // rather than a database id that means nothing on site.
        DB::statement(
            'CREATE UNIQUE INDEX incidents_event_reference_unique
             ON incidents (event_id, reference)
             WHERE deleted_at IS NULL'
        );

        Schema::create('incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->text('note')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->nullable();

            $table->index(['incident_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_updates');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('event_tasks');
        Schema::dropIfExists('task_template_items');
        Schema::dropIfExists('task_templates');
    }
};
