<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where push notifications are sent.
     *
     * Subscriptions are held against the subscriber rather than a user account, because most
     * push recipients here are not users: an attendee identified by a magic link, a staff
     * member on a shift, and an ops device all need reaching, and only one of them has a login.
     *
     * @see docs/arzo-master-plan/44-push-notifications.md
     */
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->string('subscriber_type', 16);
            $table->unsignedBigInteger('subscriber_id');
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('platform', 8);

            // Web push needs the endpoint plus the p256dh and auth keys; FCM and APNs need a
            // token. Both shapes live here because the alternative is two tables that every
            // fan-out has to union.
            $table->text('endpoint')->nullable();
            $table->text('token')->nullable();
            $table->jsonb('keys')->nullable();

            $table->jsonb('categories')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('locale', 12)->default('en');
            $table->timestampTz('last_success_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);

            // Revoked rather than deleted: a subscription that died is evidence of why a
            // recipient stopped being reachable, which a deleted row cannot tell anybody.
            $table->timestampTz('revoked_at')->nullable();
            $table->string('revoked_reason', 64)->nullable();

            $table->timestamps();

            $table->index(['subscriber_type', 'subscriber_id']);
            $table->index(['event_id', 'revoked_at']);
        });

        DB::statement("
            ALTER TABLE push_subscriptions
            ADD CONSTRAINT push_subscriptions_subscriber_type_valid
            CHECK (subscriber_type IN ('ATTENDEE', 'USER', 'DEVICE'))
        ");

        DB::statement("
            ALTER TABLE push_subscriptions
            ADD CONSTRAINT push_subscriptions_platform_valid
            CHECK (platform IN ('WEB', 'FCM', 'APNS'))
        ");

        // A web subscription has an endpoint, a native one has a token. Neither is optional,
        // and a row with both null is a subscription nothing can be sent to.
        DB::statement("
            ALTER TABLE push_subscriptions
            ADD CONSTRAINT push_subscriptions_addressable
            CHECK (
                (platform = 'WEB' AND endpoint IS NOT NULL)
                OR (platform IN ('FCM', 'APNS') AND token IS NOT NULL)
            )
        ");

        // One live subscription per endpoint. The same browser re-subscribing after a
        // permission reset sends the same endpoint, and two live rows would push twice.
        DB::statement('
            CREATE UNIQUE INDEX push_subscriptions_live_endpoint_unique
            ON push_subscriptions (md5(endpoint))
            WHERE endpoint IS NOT NULL AND revoked_at IS NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX push_subscriptions_live_token_unique
            ON push_subscriptions (md5(token))
            WHERE token IS NOT NULL AND revoked_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
