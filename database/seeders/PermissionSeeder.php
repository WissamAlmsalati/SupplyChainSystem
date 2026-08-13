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
            'cafes',
            'cafe_branches',
            'categories',
            'products',
            'product_variants',
            'product_images',
            'inventory',
            'orders',
            'purchase_orders',
            'suppliers',
            'warehouses',
            'delivery_zones',
            'users',
            'user_types',
            'permissions',
            'activity_logs',
        ];

        $codes = [
            'ORDER_ASSIGN',
            'ZONE_MANAGE',
            'DASHBOARD_VIEW',
        ];

        foreach ($modules as $module) {
            foreach (['view', 'create', 'edit', 'delete'] as $op) {
                $codes[] = strtoupper($module) . '_' . strtoupper($op);
            }
        }

        foreach ($codes as $code) {
            Permission::firstOrCreate(['code' => $code]);
        }

        $superAdmin = UserType::where('name', 'super_admin')->first();
        $admin = UserType::where('name', 'admin')->first();
        $cafe = UserType::where('name', 'cafe')->first();
        $delegate = UserType::where('name', 'delegate')->first();

        $all = Permission::all();
        $superAdmin?->permissions()->sync($all);
        $admin?->permissions()->sync($all);

        $cafe?->permissions()->sync(
            Permission::whereIn('code', [
                'ORDERS_VIEW',
                'ORDERS_EDIT',
                'CAFE_BRANCHES_VIEW',
                'CAFE_BRANCHES_CREATE',
                'CAFE_BRANCHES_EDIT',
                'CAFE_BRANCHES_DELETE',
                'INVENTORY_VIEW',
            ])->pluck('id')
        );

        $delegate?->permissions()->sync(
            Permission::whereIn('code', ['ORDER_ASSIGN', 'ORDERS_VIEW'])->pluck('id')
        );
    }
}
