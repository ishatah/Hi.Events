<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION = 'networking.manage';

    public function up(): void
    {
        if (DB::table('permissions')->where('name', self::PERMISSION)->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => self::PERMISSION,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('permission_role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
