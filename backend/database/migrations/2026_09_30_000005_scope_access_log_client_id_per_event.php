<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE access_logs DROP CONSTRAINT IF EXISTS access_logs_client_generated_id_unique');

        DB::statement(
            'CREATE UNIQUE INDEX access_logs_event_client_generated_id_unique
             ON access_logs (event_id, client_generated_id)
             WHERE client_generated_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS access_logs_event_client_generated_id_unique');

        DB::statement(
            'ALTER TABLE access_logs
             ADD CONSTRAINT access_logs_client_generated_id_unique UNIQUE (client_generated_id)'
        );
    }
};
