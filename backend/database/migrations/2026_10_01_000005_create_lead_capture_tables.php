<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors the access_logs / access_grants split: an append-only record of what happened,
     * and a mutable working record derived from it.
     *
     * @see docs/arzo-master-plan/33-exhibitor-lead-capture.md
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_exhibitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();

            // The accountability record: exactly what was transferred, at capture time. The
            // exhibitor's view reads this rather than the live person row, so what they hold
            // is provable after the fact and unaffected by later profile edits.
            $table->jsonb('shared_fields');

            $table->timestampTz('first_captured_at');
            $table->timestampTz('last_captured_at');
            $table->integer('capture_count')->default(1);
            $table->string('rating', 16)->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('qualification')->nullable();
            $table->string('status', 16)->default('NEW');
            $table->timestampTz('exported_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_exhibitor_id', 'status']);
        });

        // A rescan appends a capture and bumps the count; it does not create a second lead.
        DB::statement(
            'CREATE UNIQUE INDEX leads_exhibitor_person_unique
             ON leads (event_exhibitor_id, person_id)
             WHERE deleted_at IS NULL'
        );

        Schema::create('lead_captures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_exhibitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('captured_by_exhibitor_staff_id')->nullable()
                ->constrained('exhibitor_staff')->nullOnDelete();

            // Hashed, never raw. A badge QR is a working access credential, so a booth
            // phone's queue of raw codes would be a bag of admissions if the phone were lost.
            $table->string('identifier_hash', 64);
            $table->string('identifier_type', 16)->default('QR');

            $table->timestampTz('captured_at');
            $table->timestampTz('recorded_at');
            $table->uuid('client_generated_id')->nullable();
            $table->string('resolution', 24);
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['event_exhibitor_id', 'captured_at']);
        });

        // Scoped per event for the same reason access_logs is: a client id generated on one
        // booth phone must not collide with, or resolve to, another event's capture.
        DB::statement(
            'CREATE UNIQUE INDEX lead_captures_event_client_id_unique
             ON lead_captures (event_id, client_generated_id)
             WHERE client_generated_id IS NOT NULL'
        );

        // A purpose-bound, timestamped record of consent to share data with exhibitors.
        // Order-level marketing opt-in is consent to the organizer's marketing, which is a
        // different purpose and cannot stand in for this.
        Schema::create('lead_consents', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('event_exhibitor_id')->nullable()
                ->constrained()->cascadeOnDelete();
            $table->string('purpose', 64);
            $table->jsonb('shared_field_names');
            $table->text('consent_text');
            $table->timestampTz('granted_at');
            $table->timestampTz('withdrawn_at')->nullable();
            $table->string('source', 32)->default('CHECKOUT');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['event_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_consents');
        Schema::dropIfExists('lead_captures');
        Schema::dropIfExists('leads');
    }
};
