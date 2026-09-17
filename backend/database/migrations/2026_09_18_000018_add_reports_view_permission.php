<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Reports are a new guarded module; existing admin roles get the code.
return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('permissions')->where('code', 'REPORTS_VIEW')->value('id')
            ?? DB::table('permissions')->insertGetId(['code' => 'REPORTS_VIEW', 'created_at' => now(), 'updated_at' => now()]);

        foreach (DB::table('user_types')->whereIn('name', ['super_admin', 'admin'])->pluck('id') as $typeId) {
            DB::table('user_type_permission')->updateOrInsert(['user_type_id' => $typeId, 'permission_id' => $id], []);
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('code', 'REPORTS_VIEW')->value('id');
        if ($id) {
            DB::table('user_type_permission')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
