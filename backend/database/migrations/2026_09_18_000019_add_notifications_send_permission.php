<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Dashboard announcements need their own code; existing admin roles get it.
return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('permissions')->where('code', 'NOTIFICATIONS_SEND')->value('id')
            ?? DB::table('permissions')->insertGetId(['code' => 'NOTIFICATIONS_SEND', 'created_at' => now(), 'updated_at' => now()]);

        foreach (DB::table('user_types')->whereIn('name', ['super_admin', 'admin'])->pluck('id') as $typeId) {
            DB::table('user_type_permission')->updateOrInsert(['user_type_id' => $typeId, 'permission_id' => $id], []);
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('code', 'NOTIFICATIONS_SEND')->value('id');
        if ($id) {
            DB::table('user_type_permission')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
