<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The notification bus: who, when, on which channel, and what happened.
     *
     * Deliberately not Laravel Notifications. Most recipients here are not users — they are
     * attendees, persons, order buyers, staff on a shift — and what the messaging plans need
     * is the delivery log, suppression, quiet hours and fallback, none of which routing
     * provides. Adopting it would add a layer and leave all four still to build.
     *
     * @see docs/arzo-master-plan/69-notifications-architecture.md
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('category', 32);
            $table->string('template_key', 128);

            // Allowlisted template variables only. A context that can carry anything becomes
            // a way to render data the recipient should not see.
            $table->jsonb('context')->nullable();

            // The audience definition, kept for audit. The recipients themselves are frozen
            // as delivery rows, because re-resolving later would answer a different question.
            $table->jsonb('audience')->nullable();

            $table->string('triggered_by_type', 16)->default('SYSTEM');
            $table->unsignedBigInteger('triggered_by_id')->nullable();

            // A reminder for a session that has already started is dropped, not sent late.
            $table->timestampTz('expires_at')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'category']);
            $table->index(['event_id', 'created_at']);
        });

        DB::statement("
            ALTER TABLE notifications
            ADD CONSTRAINT notifications_category_valid
            CHECK (category IN (
                'CRITICAL_OPERATIONAL', 'OPERATIONAL', 'REMINDER', 'ACCOUNT', 'STAFF_ALERT'
            ))
        ");

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->string('recipient_type', 16);
            $table->unsignedBigInteger('recipient_id');
            $table->string('channel', 16);

            // The address lives on the recipient. Here it is hashed for matching against
            // suppressions, and masked so support can confirm which address was used without
            // the log becoming a contact database.
            $table->string('address_hash', 64);
            $table->string('address_masked', 128);

            $table->string('status', 16)->default('PENDING');
            $table->string('provider', 64)->nullable();
            $table->string('provider_message_id', 191)->nullable();
            $table->foreignId('fallback_of_id')->nullable()
                ->constrained('notification_deliveries')->nullOnDelete();
            $table->string('error_code', 64)->nullable();
            $table->string('error_detail', 500)->nullable();
            $table->timestampTz('queued_at')->useCurrent();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('deferred_until')->nullable();
            $table->timestamps();

            $table->index(['notification_id', 'status']);
            $table->index(['recipient_type', 'recipient_id']);
            $table->index(['status', 'deferred_until']);
        });

        // Makes fan-out idempotent: a retried job cannot create a second delivery, which for
        // SMS and WhatsApp would mean paying twice to interrupt somebody twice.
        DB::statement('
            CREATE UNIQUE INDEX notification_deliveries_unique_recipient_channel
            ON notification_deliveries (notification_id, recipient_type, recipient_id, channel)
        ');

        DB::statement('
            CREATE UNIQUE INDEX notification_deliveries_provider_message_unique
            ON notification_deliveries (provider, provider_message_id)
            WHERE provider IS NOT NULL AND provider_message_id IS NOT NULL
        ');

        DB::statement("
            ALTER TABLE notification_deliveries
            ADD CONSTRAINT notification_deliveries_channel_valid
            CHECK (channel IN ('EMAIL', 'SMS', 'WHATSAPP', 'PUSH', 'IN_APP'))
        ");

        DB::statement("
            ALTER TABLE notification_deliveries
            ADD CONSTRAINT notification_deliveries_status_valid
            CHECK (status IN (
                'PENDING', 'SUPPRESSED', 'DEFERRED', 'SENDING', 'SENT', 'DELIVERED',
                'READ', 'FAILED', 'BOUNCED', 'EXPIRED', 'UNKNOWN'
            ))
        ");

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();

            // All three null is a system default. Resolution walks event, then organizer,
            // then account, then system, mirroring what email templates already do.
            $table->foreignId('account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('organizer_id')->nullable()->constrained('organizers')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();

            $table->string('template_key', 128);
            $table->string('channel', 16);
            $table->string('locale', 12)->default('en');
            $table->string('subject', 500)->nullable();
            $table->text('body');

            // WhatsApp templates are approved by the provider before use, so the body here is
            // for preview and the provider's own reference is what gets sent.
            $table->string('provider_template_ref', 191)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['template_key', 'channel', 'locale']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('category', 32);
            $table->string('channel', 16);
            $table->boolean('enabled')->default(true);
            $table->string('source', 24)->default('SELF');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });

        DB::statement('
            CREATE UNIQUE INDEX notification_preferences_unique
            ON notification_preferences (subject_type, subject_id, COALESCE(event_id, 0), category, channel)
        ');

        Schema::create('channel_suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('address_hash', 64);
            $table->string('scope', 16)->default('ACCOUNT');
            $table->string('reason', 24);
            $table->string('detail', 500)->nullable();
            $table->timestamps();

            $table->index(['channel', 'address_hash']);
        });

        // One place for an email bounce and an SMS STOP, so a person who opted out of one
        // channel is not quietly still reachable on it through another code path.
        DB::statement('
            CREATE UNIQUE INDEX channel_suppressions_unique
            ON channel_suppressions (channel, address_hash, COALESCE(account_id, 0), scope)
        ');

        DB::statement("
            ALTER TABLE channel_suppressions
            ADD CONSTRAINT channel_suppressions_reason_valid
            CHECK (reason IN ('UNSUBSCRIBE', 'STOP_REPLY', 'BOUNCE', 'COMPLAINT', 'MANUAL'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_suppressions');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
    }
};
