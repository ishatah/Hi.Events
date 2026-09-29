<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Findings recorded beside an access log, never by editing it.
     *
     * An access log is evidence. A system that rewrites its own audit trail to look
     * consistent is worse than one that reports the inconsistency — so an offline scan of a
     * credential that turned out to be revoked stays GRANTED in the log, and the problem is
     * recorded here instead.
     *
     * @see docs/arzo-master-plan/71-realtime-architecture.md
     */
    public function up(): void
    {
        Schema::create('access_reconciliation_findings', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_log_id')->constrained('access_logs')->cascadeOnDelete();
            $table->foreignId('credential_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('finding_type', 40);
            $table->string('severity', 16);
            $table->text('detail');
            $table->jsonb('context')->nullable();
            $table->string('status', 16)->default('OPEN');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestampTz('detected_at');
            $table->timestamps();

            $table->index(['event_id', 'status', 'severity']);
            $table->index('access_log_id');
        });

        // One finding of a given type per log. Re-running reconciliation must not pile up
        // duplicates of the same problem, or the review queue becomes unusable.
        DB::statement(
            'CREATE UNIQUE INDEX access_reconciliation_findings_unique
             ON access_reconciliation_findings (access_log_id, finding_type)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('access_reconciliation_findings');
    }
};
