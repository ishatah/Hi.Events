<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('parent_zone_id')->nullable()->constrained('zones')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('code', 64);
            $table->string('zone_type', 32)->default('GENERAL');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('colour', 16)->nullable();
            $table->boolean('requires_credential')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('venue_id');
            $table->index('parent_zone_id');
            $table->index(['venue_id', 'zone_type']);
        });

        DB::statement('
            CREATE UNIQUE INDEX zones_venue_code_unique
            ON zones (venue_id, code)
            WHERE deleted_at IS NULL
        ');

        Schema::create('access_points', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('code', 64);
            $table->string('direction', 16)->default('BIDIRECTIONAL');
            $table->string('access_point_type', 32)->default('DOOR');
            $table->boolean('is_active')->default(true);
            $table->jsonb('position')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('zone_id');
            $table->index(['zone_id', 'is_active']);
        });

        DB::statement('
            CREATE UNIQUE INDEX access_points_zone_code_unique
            ON access_points (zone_id, code)
            WHERE deleted_at IS NULL
        ');

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->string('name', 255);
            $table->string('code', 64)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('room_type', 32)->default('GENERAL');
            $table->jsonb('layout')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('venue_id');
            $table->index('floor_id');
            $table->index('zone_id');
        });

        Schema::create('event_venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('event_occurrence_id')->nullable()->constrained('event_occurrences')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->index('event_id');
            $table->index('venue_id');
            $table->index('event_occurrence_id');
        });

        DB::statement('
            CREATE UNIQUE INDEX event_venues_unique
            ON event_venues (event_id, venue_id, COALESCE(event_occurrence_id, 0))
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('event_venues');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('access_points');
        Schema::dropIfExists('zones');
    }
};
