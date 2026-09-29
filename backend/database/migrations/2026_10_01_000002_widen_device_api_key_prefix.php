<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * devices.api_key_prefix was 16 characters, but a device prefix is 'arzod_' plus 12
     * characters of identifier — 18. No device key could be stored at all, which only
     * surfaced when the shared hashing scheme tried to write one.
     *
     * Matched to api_keys.key_prefix so the two stores stay interchangeable.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE devices ALTER COLUMN api_key_prefix TYPE varchar(24)');

        DB::statement(
            'CREATE UNIQUE INDEX devices_active_api_key_prefix_unique
             ON devices (api_key_prefix)
             WHERE api_key_prefix IS NOT NULL AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS devices_active_api_key_prefix_unique');
        DB::statement('ALTER TABLE devices ALTER COLUMN api_key_prefix TYPE varchar(16)');
    }
};
