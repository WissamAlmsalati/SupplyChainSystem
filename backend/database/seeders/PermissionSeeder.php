<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\UserType;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            // addresses routes check the historic CAFE_BRANCHES_* codes (see CheckPermission)
            'cafe_branches',
            'categories',
            'products',
            'product_variants',
            'product_images',
            'featured_sections',
            'inventory',
            'stock_movements',
            'orders',
            'payments',
            'wallets',
            'wallet_topups',
            'custody',
            'warehouses',
            'delivery_zones',
            'users',
            'delegates',
            'user_types',
            'permissions',
            'promos',
            'activity_logs',
        ];

        $codes = [
            'ORDER_ASSIGN',
            'ZONE_MANAGE',
            'DASHBOARD_VIEW',
            'CAFE_REGISTRATIONS_VIEW',
            'CAFE_REGISTRATIONS_APPROVE',
        ];

        foreach ($modules as $module) {
            foreach (['view', 'create', 'edit', 'delete'] as $op) {
                $codes[] = strtoupper($module) . '_' . strtoupper($op);
            }
        }

        foreach ($codes as $code) {
            Permission::firstOrCreate(['code' => $code]);
        }

        $all = Permission::all();
        UserType::where('name', 'super_admin')->first()?->permissions()->sync($all);
        UserType::where('name', 'admin')->first()?->permissions()->sync($all);

        UserType::where('name', 'cafe')->first()?->permissions()->sync(
            Permission::whereIn('code', [
                'ORDERS_VIEW',
                'ORDERS_CREATE',
                'CAFE_BRANCHES_VIEW',
                'CAFE_BRANCHES_CREATE',
                'CAFE_BRANCHES_EDIT',
                'CAFE_BRANCHES_DELETE',
                'INVENTORY_VIEW',
            ])->pluck('id')
        );

        UserType::where('name', 'delegate')->first()?->permissions()->sync(
            Permission::whereIn('code', ['ORDER_ASSIGN', 'ORDERS_VIEW'])->pluck('id')
        );
    }
}
