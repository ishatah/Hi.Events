<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key_prefix', 24);
            $table->string('key_hash');
            $table->jsonb('scopes');
            $table->integer('rate_limit_per_minute')->nullable();
            $table->jsonb('allowed_ips')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'revoked_at']);
        });

        // The prefix is what the authenticator looks a key up by before it can verify the
        // hash, so it has to be unique across live keys or a lookup could resolve the
        // wrong row. Partial, because a revoked key keeps its prefix for the audit trail.
        DB::statement(
            'CREATE UNIQUE INDEX api_keys_active_prefix_unique
             ON api_keys (key_prefix)
             WHERE revoked_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
