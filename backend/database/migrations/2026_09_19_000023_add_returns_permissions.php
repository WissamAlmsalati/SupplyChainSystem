<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Returns are a new guarded module; existing admin roles get its codes.
return new class extends Migration
{
    private const CODES = ['RETURNS_VIEW', 'RETURNS_CREATE'];

    public function up(): void
    {
        $types = DB::table('user_types')->whereIn('name', ['super_admin', 'admin'])->pluck('id');

        foreach (self::CODES as $code) {
            $id = DB::table('permissions')->where('code', $code)->value('id')
                ?? DB::table('permissions')->insertGetId(['code' => $code, 'created_at' => now(), 'updated_at' => now()]);

            foreach ($types as $typeId) {
                DB::table('user_type_permission')->updateOrInsert(['user_type_id' => $typeId, 'permission_id' => $id], []);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('code', self::CODES)->pluck('id');
        DB::table('user_type_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
