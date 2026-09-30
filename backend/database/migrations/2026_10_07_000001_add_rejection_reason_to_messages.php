<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why a reviewer refused a message.
     *
     * A column rather than a note folded into `send_data`, because the organizer asking why
     * their message never went out is the person who needs it, and a reason buried in a
     * send-metadata blob is not something support can read back to them.
     *
     * @see docs/arzo-master-plan/136-master-backlog.md ARZ-305
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('rejection_reason', 500)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
