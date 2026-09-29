<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @see docs/arzo-master-plan/40-device-management.md
     * @see docs/arzo-master-plan/36-rfid-nfc.md
     * @see docs/arzo-master-plan/39-printer-integration.md
     */
    public function up(): void
    {
        // Queued rather than pushed. Devices are often offline, so a command waits to be
        // collected on the next sync. A stolen device that never reconnects never receives a
        // wipe — that is stated plainly in the plan and is why wipe-on-command is a
        // convenience for recovered hardware, not a breach control.
        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('command', 32);
            $table->jsonb('payload')->nullable();
            $table->string('status', 16)->default('PENDING');
            $table->timestampTz('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->text('result')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'status']);
        });

        // A heartbeat every few seconds from every device would be volume with no readers, so
        // only state changes are appended and the current picture lives on the device row.
        Schema::create('device_health_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('state', 24);
            $table->string('reason')->nullable();
            $table->integer('battery_level')->nullable();
            $table->integer('clock_skew_seconds')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->nullable();

            $table->index(['device_id', 'occurred_at']);
        });

        // A credential may be carried by several physical media over its life: a printed QR,
        // a badge inlay, a replacement wristband after the first is lost. Two scalar columns
        // on credentials cannot record that history.
        Schema::create('credential_media', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('credential_id')->constrained()->cascadeOnDelete();
            $table->string('media_type', 16);
            $table->string('uid_hash', 64)->nullable();
            $table->string('status', 16)->default('ACTIVE');
            $table->timestampTz('encoded_at')->nullable();
            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('encoded_on_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->timestampTz('deactivated_at')->nullable();
            $table->string('deactivation_reason')->nullable();
            $table->foreignId('replaces_media_id')->nullable()
                ->constrained('credential_media')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['credential_id', 'status']);
        });

        // A physical tag maps to at most one live credential. Two active credentials behind
        // one wristband is a cloned badge, which is the thing this subsystem exists to make
        // impossible. Partial, because a deactivated tag keeps its history.
        DB::statement(
            "CREATE UNIQUE INDEX credential_media_active_uid_unique
             ON credential_media (uid_hash)
             WHERE uid_hash IS NOT NULL AND status = 'ACTIVE'"
        );

        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();

            // The print host the printer is attached to. The server never connects into the
            // venue LAN; the host pulls work, which is why a printer is reachable only
            // through its host.
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('identifier', 64);
            $table->string('printer_type', 24);
            $table->string('status', 16)->default('UNKNOWN');
            $table->string('media_size', 32)->nullable();
            $table->integer('dpi')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->string('last_error')->nullable();
            $table->jsonb('capabilities')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'status']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX printers_account_identifier_unique
             ON printers (account_id, identifier)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
        Schema::dropIfExists('credential_media');
        Schema::dropIfExists('device_health_events');
        Schema::dropIfExists('device_commands');
    }
};
