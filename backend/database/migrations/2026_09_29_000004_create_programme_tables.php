<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('colour', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
        });

        Schema::create('speakers', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('first_name', 128);
            $table->string('last_name', 128)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('title', 255)->nullable();
            $table->string('company', 255)->nullable();
            $table->text('bio')->nullable();
            $table->foreignId('photo_image_id')->nullable()->constrained('images')->nullOnDelete();
            $table->jsonb('social')->nullable();
            $table->boolean('is_published')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index('event_id');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_occurrence_id')->nullable()->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained('tracks')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('session_type', 32)->default('TALK');
            $table->string('status', 32)->default('DRAFT');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('requires_registration')->default(false);
            $table->timestampTz('registration_opens_at')->nullable();
            $table->timestampTz('registration_closes_at')->nullable();
            $table->boolean('allow_waitlist')->default(false);
            $table->boolean('check_in_enabled')->default(false);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
            $table->index('event_occurrence_id');
            $table->index('track_id');
            $table->index('room_id');
            $table->index(['event_id', 'starts_at']);
        });

        // A published session must not share a room with another published session at an
        // overlapping time. Enforced by the database rather than by application checks,
        // because a double-booked room is discovered on the day.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement('
            ALTER TABLE sessions
            ADD CONSTRAINT sessions_no_room_overlap
            EXCLUDE USING gist (
                room_id WITH =,
                tstzrange(starts_at, ends_at, \'[)\') WITH &&
            )
            WHERE (deleted_at IS NULL AND is_published = true AND room_id IS NOT NULL)
        ');

        DB::statement('
            ALTER TABLE sessions
            ADD CONSTRAINT sessions_ends_after_starts
            CHECK (ends_at > starts_at)
        ');

        Schema::create('session_speakers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignId('speaker_id')->constrained('speakers')->cascadeOnDelete();
            $table->string('role', 32)->default('SPEAKER');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['session_id', 'speaker_id']);
            $table->index('speaker_id');
        });

        Schema::create('session_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_products');
        Schema::dropIfExists('session_speakers');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('speakers');
        Schema::dropIfExists('tracks');
    }
};
