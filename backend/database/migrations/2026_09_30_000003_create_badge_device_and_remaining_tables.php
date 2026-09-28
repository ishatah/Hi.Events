<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The remaining tables specified by documents 12, 14, 19, 20 and 21.
     *
     * @see docs/arzo-master-plan/21-badge-management.md
     * @see docs/arzo-master-plan/40-device-management.md
     * @see docs/arzo-master-plan/14-waitlist-and-capacity.md
     * @see docs/arzo-master-plan/12-rsvp-registration.md
     * @see docs/arzo-master-plan/20-queue-management.md
     */
    public function up(): void
    {
        $this->createBadgeTables();
        $this->createDeviceTables();
        $this->createSessionWaitlistTable();
        $this->createRsvpTables();
        $this->createThroughputTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('access_point_throughput_snapshots');
        Schema::dropIfExists('rsvp_responses');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('session_waitlist_entries');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('badge_print_jobs');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('badge_templates');
    }

    private function createBadgeTables(): void
    {
        Schema::create('badge_templates', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('width_mm', 8, 2)->default(54);
            $table->decimal('height_mm', 8, 2)->default(86);
            $table->string('orientation', 16)->default('PORTRAIT');
            $table->unsignedInteger('dpi')->default(300);

            // Positioned element tree: TEXT, FIELD, QR, BARCODE, PHOTO, IMAGE, SHAPE,
            // ZONE_COLOUR_BAR. Each element carries position, size, style and binding.
            $table->jsonb('layout')->nullable();

            $table->foreignId('background_image_id')->nullable()->constrained('images')->nullOnDelete();
            $table->boolean('is_default')->default(false);

            // A reprint must reproduce what was originally printed, not the current
            // template, or a reissued badge differs visibly from its neighbours.
            $table->unsignedInteger('version')->default(1);

            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index('event_id');
        });

        Schema::table('accreditation_types', function (Blueprint $table) {
            $table->foreignId('badge_template_id')->nullable()->after('max_issuable')
                ->constrained('badge_templates')->nullOnDelete();
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->foreignId('badge_template_id')->nullable()->constrained('badge_templates')->nullOnDelete();
            $table->unsignedInteger('template_version')->default(1);
            $table->string('status', 16)->default('PENDING');
            $table->string('rendered_pdf_path', 512)->nullable();
            $table->timestampTz('rendered_at')->nullable();
            $table->timestampTz('printed_at')->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->foreignId('replaces_badge_id')->nullable()->constrained('badges')->nullOnDelete();

            // The field values at print time. Names and titles change; a photo of a badge
            // in an incident report must be reconcilable with the record.
            $table->jsonb('snapshot')->nullable();

            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index('credential_id');
            $table->index(['event_id', 'status']);
        });

        Schema::create('badge_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('badge_id')->constrained('badges')->cascadeOnDelete();
            $table->string('printer_identifier', 255)->nullable();
            $table->string('status', 16)->default('QUEUED');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('queued_at')->useCurrent();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();

            // Offline idempotency: replaying a queued print is a no-op on conflict.
            $table->uuid('client_generated_id')->nullable()->unique();

            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'queued_at']);
            $table->index('badge_id');
        });
    }

    private function createDeviceTables(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->foreignId('access_point_id')->nullable()->constrained('access_points')->nullOnDelete();
            $table->string('name', 255);
            $table->string('device_type', 32)->default('SCANNER');
            $table->string('platform', 64)->nullable();
            $table->string('app_version', 32)->nullable();

            // Devices authenticate with their own scoped key, never a user's JWT: a shared
            // user token on twenty tablets cannot be revoked per device.
            $table->string('api_key_prefix', 16)->nullable();
            $table->string('api_key_hash', 255)->nullable();

            $table->string('pairing_code', 16)->nullable();
            $table->timestampTz('pairing_code_expires_at')->nullable();
            $table->string('status', 16)->default('PENDING');
            $table->timestampTz('last_seen_at')->nullable();
            $table->string('last_sync_cursor', 64)->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index(['event_id', 'status']);
            $table->index('api_key_prefix');
        });

        DB::statement('
            CREATE UNIQUE INDEX devices_pairing_code_unique
            ON devices (pairing_code)
            WHERE pairing_code IS NOT NULL AND deleted_at IS NULL
        ');
    }

    private function createSessionWaitlistTable(): void
    {
        // A sibling of waitlist_entries rather than an extension of it: that table's
        // product_id is NOT NULL and its whole shape is purchase-oriented
        // (offer_token, purchased_at, order_id), none of which applies to a session.
        Schema::create('session_waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('attendees')->cascadeOnDelete();
            $table->string('status', 32)->default('PENDING');
            $table->integer('position')->default(0);
            $table->timestampTz('offered_at')->nullable();
            $table->timestampTz('offer_expires_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('offer_token', 100)->unique()->nullable();
            $table->string('cancel_token', 100)->unique()->nullable();
            $table->string('locale', 10)->default('en');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['session_id', 'status']);
            $table->index('attendee_id');
        });

        DB::statement('
            CREATE UNIQUE INDEX session_waitlist_entries_unique
            ON session_waitlist_entries (session_id, attendee_id)
            WHERE deleted_at IS NULL
        ');
    }

    private function createRsvpTables(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->string('first_name', 128);
            $table->string('last_name', 128)->nullable();
            $table->string('email', 255);
            $table->string('phone', 64)->nullable();
            $table->string('token', 100)->unique();
            $table->unsignedInteger('max_party_size')->default(1);
            $table->string('status', 32)->default('PENDING');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
        });

        DB::statement('
            CREATE UNIQUE INDEX invitations_event_email_unique
            ON invitations (event_id, lower(email))
            WHERE deleted_at IS NULL
        ');

        Schema::create('rsvp_responses', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('invitation_id')->constrained('invitations')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            // Declining is a first-class answer. An order cannot represent "no", which is
            // the whole reason RSVP is a separate flow from free ticketing.
            $table->string('response', 16);

            $table->unsignedInteger('party_size')->default(1);
            $table->timestampTz('responded_at')->useCurrent();
            $table->ipAddress('responded_from_ip')->nullable();
            $table->jsonb('form_data')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique('invitation_id');
            $table->index(['event_id', 'response']);
        });

        DB::statement("
            ALTER TABLE rsvp_responses
            ADD CONSTRAINT rsvp_responses_response_valid
            CHECK (response IN ('ATTENDING', 'NOT_ATTENDING', 'TENTATIVE'))
        ");
    }

    private function createThroughputTable(): void
    {
        Schema::create('access_point_throughput_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_point_id')->constrained('access_points')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->timestampTz('window_start');
            $table->timestampTz('window_end');
            $table->unsignedInteger('scans_granted')->default(0);

            // Denials are counted because a gate denying many scans is slower, not faster:
            // each denial consumes staff time. Counting only successes would show a
            // congested door as quiet.
            $table->unsignedInteger('scans_denied')->default(0);

            $table->decimal('median_service_seconds', 8, 2)->nullable();
            $table->unsignedInteger('estimated_queue_depth')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['access_point_id', 'window_start']);
            $table->index(['event_id', 'window_start']);
        });
    }
};
