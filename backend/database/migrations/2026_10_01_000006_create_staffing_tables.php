<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @see docs/arzo-master-plan/57-manpower-and-staffing.md
     */
    public function up(): void
    {
        Schema::create('staff_positions', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('access_point_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('accreditation_type_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('required_skills')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('staff_position_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->integer('required_headcount')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index('staff_position_id');
        });

        DB::statement('ALTER TABLE shifts ADD CONSTRAINT shifts_end_after_start CHECK (ends_at > starts_at)');

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('accreditation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16);

            // Copied from the shift because the exclusion constraint needs the range on this
            // row. A shift time change updates its assignments in the same transaction.
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');

            $table->timestampTz('checked_in_at')->nullable();
            $table->timestampTz('checked_out_at')->nullable();
            $table->timestamps();

            $table->index(['shift_id', 'status']);
            $table->index('person_id');
        });

        // A person cannot be in two places at once. The same pattern sessions uses for
        // rooms, applied to people: the database refuses an overlap rather than trusting a
        // rota screen to notice. Declined and cancelled rows are excluded because they are
        // no longer a commitment.
        DB::statement(
            "ALTER TABLE shift_assignments ADD CONSTRAINT shift_assignments_no_person_overlap
             EXCLUDE USING gist (
                person_id WITH =,
                tstzrange(starts_at, ends_at, '[)') WITH &&
             )
             WHERE (status NOT IN ('DECLINED', 'CANCELLED'))"
        );

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->jsonb('languages')->nullable();
            $table->jsonb('skills')->nullable();
            $table->jsonb('certifications')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::statement(
            'CREATE UNIQUE INDEX staff_profiles_person_account_unique
             ON staff_profiles (person_id, account_id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('staff_positions');
    }
};
