<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const BATCH_SIZE = 500;

    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table): void {
            $table->renameColumn('raw_identifier', 'identifier_hash');
        });

        DB::table('access_logs')
            ->whereNotNull('identifier_hash')
            ->orderBy('id')
            ->chunkById(self::BATCH_SIZE, function ($logs): void {
                foreach ($logs as $log) {
                    $value = (string) $log->identifier_hash;

                    if (preg_match('/^[0-9a-f]{64}$/', $value) === 1) {
                        continue;
                    }

                    DB::table('access_logs')
                        ->where('id', $log->id)
                        ->update([
                            'identifier_hash' => hash('sha256', Str::upper(trim($value))),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table): void {
            $table->renameColumn('identifier_hash', 'raw_identifier');
        });
    }
};
