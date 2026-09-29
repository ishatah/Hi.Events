<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Account-scoped rather than platform-wide. A shared cross-tenant company registry
        // is tempting and breaks tenant isolation, which is the same call venues made.
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('logo_image_id')->nullable()->constrained('images')->nullOnDelete();
            $table->string('country', 2)->nullable();
            $table->string('vat_number', 64)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
        });

        Schema::create('event_exhibitors', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('package_name')->nullable();
            $table->integer('staff_pass_quota')->nullable();
            $table->boolean('listing_published')->default(false);
            $table->jsonb('listing')->nullable();
            $table->decimal('contract_value', 14, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('payment_status', 32)->default('NOT_INVOICED');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX event_exhibitors_unique
             ON event_exhibitors (event_id, company_id)
             WHERE deleted_at IS NULL'
        );

        // Staff credentials come through an EXHIBITOR accreditation rather than a third
        // source on credentials: the one-of CHECK stays as designed, and approval, audit
        // and type rules are reused instead of reimplemented.
        Schema::create('exhibitor_staff', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_exhibitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('role', 16)->default('STAFF');
            $table->foreignId('accreditation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'CREATE UNIQUE INDEX exhibitor_staff_unique
             ON exhibitor_staff (event_exhibitor_id, person_id)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibitor_staff');
        Schema::dropIfExists('event_exhibitors');
        Schema::dropIfExists('companies');
    }
};
