<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accreditation: the approval workflow for a category of person, and the credential
     * it issues.
     *
     * Three concepts kept distinct on purpose, because conflating them is why
     * accreditation systems get rebuilt:
     *   type        - a category (VIP, Media, Speaker), defined per event
     *   application - one person applying for one type, reviewed by a human
     *   credential  - the issued right of access, which a badge merely carries
     *
     * A ticket buyer gets a credential with no application: the ticket is the
     * entitlement. Only non-buyers go through approval.
     *
     * @see docs/arzo-master-plan/23-accreditation.md
     */
    public function up(): void
    {
        Schema::create('accreditation_types', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('colour', 16)->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->boolean('requires_photo')->default(false);
            $table->boolean('requires_id_document')->default(false);
            $table->jsonb('auto_approve_domains')->nullable();
            $table->timestampTz('application_opens_at')->nullable();
            $table->timestampTz('application_closes_at')->nullable();
            $table->unsignedInteger('max_issuable')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
            $table->index(['event_id', 'is_active']);
        });

        DB::statement('
            CREATE UNIQUE INDEX accreditation_types_event_code_unique
            ON accreditation_types (event_id, code)
            WHERE deleted_at IS NULL
        ');

        // The reusable part of an access policy: what a type grants, before any
        // event-specific rule overrides it.
        Schema::create('accreditation_type_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accreditation_type_id')->constrained('accreditation_types')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('sessions')->cascadeOnDelete();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->jsonb('days_of_week')->nullable();
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();
            $table->boolean('allow_reentry')->default(true);
            $table->unsignedInteger('max_entries')->nullable();
            $table->timestamps();

            $table->index('accreditation_type_id');
            $table->index('zone_id');
        });

        Schema::create('accreditations', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('accreditation_type_id')->constrained('accreditation_types')->cascadeOnDelete();
            $table->string('status', 32)->default('DRAFT');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->jsonb('form_data')->nullable();
            $table->jsonb('documents')->nullable();

            // Press routinely ask for backstage and are granted less. Storing both the
            // request and the grant preserves what was asked, which matters in disputes.
            $table->jsonb('requested_zones')->nullable();
            $table->jsonb('approved_zones')->nullable();

            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
            $table->index('person_id');
            $table->index(['event_id', 'status']);
        });

        DB::statement('
            CREATE UNIQUE INDEX accreditations_person_type_unique
            ON accreditations (event_id, person_id, accreditation_type_id)
            WHERE deleted_at IS NULL
        ');

        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();

            // Exactly one source must be set. Enforced by a CHECK below, so one access
            // engine serves ticket buyers, accredited press and staff without four
            // parallel code paths.
            $table->foreignId('accreditation_id')->nullable()->constrained('accreditations')->cascadeOnDelete();
            $table->foreignId('attendee_id')->nullable()->constrained('attendees')->cascadeOnDelete();

            $table->string('credential_type', 32)->default('ATTENDEE');
            $table->string('status', 32)->default('PENDING');

            // Opaque and random. A predictable credential identifier is a forgery vector,
            // so this is never sequential nor derived from personal data.
            $table->string('identifier', 128);
            $table->string('identifier_hash', 64);
            $table->string('rfid_uid', 128)->nullable();
            $table->string('nfc_uid', 128)->nullable();

            $table->timestampTz('issued_at')->useCurrent();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_until')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revocation_reason', 255)->nullable();

            // Lost-badge reissue chain. Anti-passback and clone detection need it.
            $table->foreignId('replaces_credential_id')->nullable()->constrained('credentials')->nullOnDelete();

            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index('person_id');
            $table->index('identifier_hash');
            $table->index(['event_id', 'status']);
        });

        DB::statement('
            CREATE UNIQUE INDEX credentials_event_identifier_unique
            ON credentials (event_id, identifier)
        ');

        DB::statement('
            ALTER TABLE credentials
            ADD CONSTRAINT credentials_exactly_one_source
            CHECK (
                (CASE WHEN accreditation_id IS NOT NULL THEN 1 ELSE 0 END)
              + (CASE WHEN attendee_id IS NOT NULL THEN 1 ELSE 0 END)
              = 1
            )
        ');

        Schema::table('access_logs', function (Blueprint $table) {
            $table->foreignId('credential_id')->nullable()->after('event_id')
                ->constrained('credentials')->nullOnDelete();
        });

        DB::statement('
            CREATE INDEX access_logs_credential_occurred_at_index
            ON access_logs (credential_id, occurred_at DESC)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS access_logs_credential_occurred_at_index');

        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credential_id');
        });

        Schema::dropIfExists('credentials');
        Schema::dropIfExists('accreditations');
        Schema::dropIfExists('accreditation_type_rules');
        Schema::dropIfExists('accreditation_types');
    }
};
