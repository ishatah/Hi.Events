<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('section', 64)->nullable();
            $table->string('row', 32)->nullable();
            $table->string('number', 32)->nullable();
            $table->string('label', 64)->nullable();
            $table->string('seat_type', 32)->default('STANDARD');
            $table->jsonb('position')->nullable();
            $table->boolean('is_accessible')->default(false);
            $table->string('status', 32)->default('AVAILABLE');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('room_id');
            $table->index(['room_id', 'status']);
        });

        DB::statement("
            CREATE UNIQUE INDEX seats_room_position_unique
            ON seats (room_id, COALESCE(section, ''), COALESCE(row, ''), COALESCE(number, ''))
            WHERE deleted_at IS NULL
        ");

        Schema::create('booths', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->string('code', 64);
            $table->string('name', 255)->nullable();
            $table->decimal('size_sqm', 10, 2)->nullable();
            $table->string('booth_type', 32)->default('STANDARD');
            $table->string('status', 32)->default('AVAILABLE');
            $table->jsonb('position')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('venue_id');
            $table->index('zone_id');
            $table->index(['venue_id', 'status']);
        });

        DB::statement('
            CREATE UNIQUE INDEX booths_venue_code_unique
            ON booths (venue_id, code)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('booths');
        Schema::dropIfExists('seats');
    }
};
