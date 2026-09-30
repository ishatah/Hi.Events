<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An invitation token is a bearer credential: whoever holds it can answer for the
     * invitee. Stored in plaintext it is readable from a backup, a replica or a support
     * query, which is the same defect ARZ-327 records for lookup tokens.
     *
     * Hashed here while the table is empty, so no live invitation link breaks.
     *
     * @see docs/arzo-master-plan/136-master-backlog.md ARZ-327
     */
    public function up(): void
    {
        $existing = DB::table('invitations')->count();

        if ($existing > 0) {
            // Existing plaintext tokens cannot be hashed without invalidating links already
            // sent, so this refuses rather than silently breaking them. Re-issue them first.
            throw new RuntimeException(
                'invitations already contains '.$existing.' row(s). '
                .'Hashing in place would invalidate links already sent; re-issue them first.'
            );
        }

        Schema::table('invitations', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable()->after('token');
        });

        DB::statement('ALTER TABLE invitations ALTER COLUMN token DROP NOT NULL');

        DB::statement(
            'CREATE UNIQUE INDEX invitations_token_hash_unique
             ON invitations (token_hash)
             WHERE token_hash IS NOT NULL AND deleted_at IS NULL'
        );

        DB::statement("
            ALTER TABLE invitations
            ADD CONSTRAINT invitations_status_valid
            CHECK (status IN ('PENDING', 'SENT', 'ATTENDING', 'NOT_ATTENDING', 'TENTATIVE', 'REVOKED'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE invitations DROP CONSTRAINT IF EXISTS invitations_status_valid');
        DB::statement('DROP INDEX IF EXISTS invitations_token_hash_unique');

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('token_hash');
        });
    }
};
