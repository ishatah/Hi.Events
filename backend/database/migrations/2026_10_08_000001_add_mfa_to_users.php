<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TOTP second factor.
     *
     * Plan 101 marks MFA as the one enterprise control that is **not** built on demand: it
     * gates any external SaaS offering, because a shared platform where one stolen password
     * reaches an organizer's whole attendee list is not something a procurement review passes.
     *
     * The secret is encrypted at rest rather than hashed, because TOTP verification needs the
     * original value — which is exactly why it must never sit in plaintext beside the password
     * hash it is meant to defend.
     *
     * @see docs/arzo-master-plan/101-enterprise.md
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('mfa_secret_encrypted')->nullable()->after('password');

            // Confirmed only once the user has proved a code from their authenticator. An
            // enrolment that is stored but never proved locks somebody out of their account.
            $table->timestampTz('mfa_confirmed_at')->nullable()->after('mfa_secret_encrypted');

            // The last accepted time step, so a replayed code inside the same window is
            // refused. Without it, a code read over somebody's shoulder works for 30 seconds.
            $table->unsignedBigInteger('mfa_last_used_timestep')->nullable()->after('mfa_confirmed_at');
        });

        Schema::create('mfa_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Hashed, because unlike the TOTP secret a recovery code only ever needs
            // comparing — so there is no reason to be able to read it back.
            $table->string('code_hash', 64);

            $table->timestampTz('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'used_at']);
        });

        DB::statement('
            CREATE UNIQUE INDEX mfa_recovery_codes_unique
            ON mfa_recovery_codes (user_id, code_hash)
        ');

        // Per account, because a user belongs to several and each organisation decides its own
        // posture. Enforced when the token is minted, which is where the account is chosen.
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('require_mfa')->default(false)->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('require_mfa');
        });

        Schema::dropIfExists('mfa_recovery_codes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_secret_encrypted', 'mfa_confirmed_at', 'mfa_last_used_timestep']);
        });
    }
};
