<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * booths was venue-scoped with an allocation status on it, which cannot express a
     * layout that changes between editions of an event, nor say who a booth is allocated
     * to. Corrected while the table is still empty.
     *
     * event_id null means a permanent venue booth; set means this event's own layout. Both
     * cases are real — a purpose-built hall has a fixed grid, a hotel ballroom is drawn
     * fresh each time.
     *
     * @see docs/arzo-master-plan/35-booth-management.md
     */
    public function up(): void
    {
        Schema::table('booths', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('venue_id')
                ->constrained()->cascadeOnDelete();
        });

        // Two editions may both number a booth "A12", so the old (venue_id, code) unique
        // index would reject the second one.
        DB::statement('DROP INDEX IF EXISTS booths_venue_code_unique');
        DB::statement(
            'CREATE UNIQUE INDEX booths_venue_event_code_unique
             ON booths (venue_id, COALESCE(event_id, 0), code)
             WHERE deleted_at IS NULL'
        );

        Schema::create('booth_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booth_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_exhibitor_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->string('role', 16)->default('PRIMARY');
            $table->string('status', 16);
            $table->timestampTz('held_until')->nullable();
            $table->timestampTz('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('released_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index('event_exhibitor_id');
        });

        // One primary occupant per booth per event. Co-exhibitors share a booth by design,
        // and a released assignment must not block the next one, so both are excluded.
        DB::statement(
            "CREATE UNIQUE INDEX booth_assignments_one_primary_unique
             ON booth_assignments (event_id, booth_id)
             WHERE status <> 'RELEASED' AND role = 'PRIMARY'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('booth_assignments');

        DB::statement('DROP INDEX IF EXISTS booths_venue_event_code_unique');

        Schema::table('booths', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX booths_venue_code_unique
             ON booths (venue_id, code)
             WHERE deleted_at IS NULL'
        );
    }
};
