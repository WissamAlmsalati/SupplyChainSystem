<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// CheckPermission is now fail-closed: every guarded route needs a seeded code.
// Existing databases get the codes that were missing, granted to admin roles.
return new class extends Migration
{
    private const CODES = [
        'CARTS_VIEW',
        'CART_ITEMS_VIEW',
        'ORDER_ITEMS_VIEW',
        'ORDER_STATUS_LOGS_VIEW',
        'PREMIUM_FEATURES_VIEW',
        'PREMIUM_FEATURES_EDIT',
    ];

    public function up(): void
    {
        $adminTypeIds = DB::table('user_types')->whereIn('name', ['super_admin', 'admin'])->pluck('id');

        foreach (self::CODES as $code) {
            $id = DB::table('permissions')->where('code', $code)->value('id')
                ?? DB::table('permissions')->insertGetId(['code' => $code, 'created_at' => now(), 'updated_at' => now()]);

            foreach ($adminTypeIds as $typeId) {
                DB::table('user_type_permission')->updateOrInsert(
                    ['user_type_id' => $typeId, 'permission_id' => $id],
                    []
                );
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
