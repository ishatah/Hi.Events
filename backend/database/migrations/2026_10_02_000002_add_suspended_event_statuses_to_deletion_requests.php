<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_deletion_requests', function (Blueprint $table): void {
            // The status each event held when the request suspended it, so cancelling the
            // request puts them back. Distinct from deletion_manifest, which records what
            // an executed deletion removed.
            $table->jsonb('suspended_event_statuses')->nullable()->after('deletion_manifest');
        });
    }

    public function down(): void
    {
        Schema::table('account_deletion_requests', function (Blueprint $table): void {
            $table->dropColumn('suspended_event_statuses');
        });
    }
};
