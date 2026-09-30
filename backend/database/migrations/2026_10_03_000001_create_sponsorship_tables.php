<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sponsorship is a separate participation from exhibiting, sharing only the company.
     *
     * A title sponsor or media partner has no booth, no stand staff and no leads; most
     * exhibitors never sponsor. Modelling sponsors as exhibitors with extra columns would
     * leave those columns empty in the common case and force booth semantics onto companies
     * that never take a stand.
     *
     * @see docs/arzo-master-plan/34-sponsor-management.md
     */
    public function up(): void
    {
        Schema::create('sponsorship_packages', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 128);
            $table->string('tier', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('price', 14, 2)->nullable();
            $table->string('currency', 3)->nullable();

            // A template, not the agreement. Real deals are negotiated away from the price
            // list, so each sponsorship instantiates its own rows and the package keeps
            // only what was advertised.
            $table->jsonb('entitlements')->nullable();

            $table->unsignedInteger('max_sponsors')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'sort_order']);
        });

        Schema::create('sponsorships', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('sponsorship_package_id')->nullable()
                ->constrained('sponsorship_packages')->nullOnDelete();

            // Denormalised because a bespoke deal may sit outside every package.
            $table->string('tier', 64);

            $table->string('status', 32)->default('PROPOSED');
            $table->decimal('contract_value', 14, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('payment_status', 32)->default('UNPAID');
            $table->string('display_name', 191)->nullable();
            $table->foreignId('logo_image_id')->nullable()->constrained('images')->nullOnDelete();
            $table->string('website_url', 255)->nullable();
            $table->boolean('show_on_event_page')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'show_on_event_page', 'display_order']);
        });

        DB::statement('
            CREATE UNIQUE INDEX sponsorships_event_company_unique
            ON sponsorships (event_id, company_id)
            WHERE deleted_at IS NULL
        ');

        DB::statement("
            ALTER TABLE sponsorships
            ADD CONSTRAINT sponsorships_status_valid
            CHECK (status IN ('PROPOSED', 'CONTRACTED', 'ACTIVE', 'FULFILLED', 'CANCELLED'))
        ");

        Schema::create('sponsorship_entitlements', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('sponsorship_id')->constrained('sponsorships')->cascadeOnDelete();
            $table->string('entitlement_type', 64);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('fulfilled_quantity')->default(0);
            $table->string('status', 32)->default('PENDING');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('fulfilled_at')->nullable();

            // What was actually delivered: image ids, promo code ids, a session id, notes.
            // Tracking promised against delivered is what a renewal conversation and a
            // dispute both turn on.
            $table->jsonb('evidence')->nullable();

            $table->timestamps();

            $table->index(['sponsorship_id', 'status']);
        });

        DB::statement("
            ALTER TABLE sponsorship_entitlements
            ADD CONSTRAINT sponsorship_entitlements_status_valid
            CHECK (status IN ('PENDING', 'IN_PROGRESS', 'FULFILLED', 'WAIVED'))
        ");

        DB::statement('
            ALTER TABLE sponsorship_entitlements
            ADD CONSTRAINT sponsorship_entitlements_fulfilled_within_quantity
            CHECK (fulfilled_quantity <= quantity)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_entitlements');
        Schema::dropIfExists('sponsorships');
        Schema::dropIfExists('sponsorship_packages');
    }
};
