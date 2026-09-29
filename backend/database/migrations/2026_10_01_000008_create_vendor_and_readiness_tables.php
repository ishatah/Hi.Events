<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @see docs/arzo-master-plan/61-vendor-management.md
     * @see docs/arzo-master-plan/59-event-readiness.md
     */
    public function up(): void
    {
        // Reuses companies rather than a parallel vendor registry: an AV company that
        // exhibits one year and supplies the next is one company, and its history across
        // events is the useful part.
        Schema::create('event_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32);
            $table->string('status', 16)->default('PROPOSED');
            $table->text('scope_of_work')->nullable();
            $table->string('contract_reference')->nullable();
            $table->foreignId('primary_contact_person_id')->nullable()
                ->constrained('persons')->nullOnDelete();
            $table->foreignId('onsite_lead_person_id')->nullable()
                ->constrained('persons')->nullOnDelete();
            $table->integer('staff_pass_quota')->nullable();
            $table->smallInteger('rating')->nullable();

            // About the company's delivery, never about individuals — a performance note on
            // a named person is an HR record and does not belong in an event system.
            $table->text('performance_notes')->nullable();

            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('rated_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
        });

        DB::statement(
            'ALTER TABLE event_vendors ADD CONSTRAINT event_vendors_rating_range
             CHECK (rating IS NULL OR (rating >= 1 AND rating <= 5))'
        );

        // One company may hold several roles at one event — a firm doing both staging and AV
        // is normal — so the category is part of the key.
        DB::statement(
            'CREATE UNIQUE INDEX event_vendors_unique
             ON event_vendors (event_id, company_id, category)
             WHERE deleted_at IS NULL'
        );

        Schema::create('vendor_staff', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('role', 16)->default('STAFF');
            $table->foreignId('accreditation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'CREATE UNIQUE INDEX vendor_staff_unique
             ON vendor_staff (event_vendor_id, person_id)
             WHERE deleted_at IS NULL'
        );

        Schema::create('readiness_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('review_point', 24);
            $table->string('decision', 16)->default('PENDING');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'review_point']);
        });

        // A snapshot, frozen when the decision is made. Checks change minute to minute; the
        // review has to record what was true at the moment somebody signed it off, so that
        // "was Gate 3's device synced at the go/no-go?" has an answer afterwards.
        Schema::create('readiness_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('readiness_review_id')->constrained()->cascadeOnDelete();
            $table->string('check_key', 64)->nullable();
            $table->foreignId('event_task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('severity', 16);
            $table->string('status', 16);
            $table->jsonb('detail')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('waiver_reason')->nullable();
            $table->timestampTz('evaluated_at');

            $table->index('readiness_review_id');
        });

        // A waiver without a reason is exactly what a post-incident review needs to find, so
        // the database refuses one rather than relying on a form to ask.
        DB::statement(
            "ALTER TABLE readiness_items ADD CONSTRAINT readiness_items_waiver_needs_reason
             CHECK (status <> 'WAIVED' OR (waiver_reason IS NOT NULL AND waiver_reason <> ''))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('readiness_items');
        Schema::dropIfExists('readiness_reviews');
        Schema::dropIfExists('vendor_staff');
        Schema::dropIfExists('event_vendors');
    }
};
