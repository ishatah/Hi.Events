<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('first_name', 128);
            $table->string('last_name', 128)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('company', 255)->nullable();
            $table->string('job_title', 255)->nullable();
            $table->string('nationality', 64)->nullable();
            $table->foreignId('photo_image_id')->nullable()->constrained('images')->nullOnDelete();

            // Collected only for accreditation at events that require identity checks.
            // Personal data under Qatar PDPL and GDPR: encrypted at the application layer,
            // excluded from exports by default. See docs/arzo-master-plan/65-privacy-gdpr.md.
            $table->text('id_document_type')->nullable();
            $table->text('id_document_number')->nullable();
            $table->text('date_of_birth')->nullable();

            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index(['account_id', 'email']);
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('id')
                ->constrained('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::dropIfExists('persons');
    }
};
