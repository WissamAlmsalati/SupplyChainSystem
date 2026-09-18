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
            // addresses routes check the historic CUSTOMER_BRANCHES_* codes (see CheckPermission)
            'customer_branches',
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

        // Read-only admin views (their rows are written by the order workflows).
        $viewOnly = ['carts', 'cart_items', 'order_items', 'order_status_logs'];

        $codes = [
            'ORDER_ASSIGN',
            'ZONE_MANAGE',
            'DASHBOARD_VIEW',
            'CUSTOMER_REGISTRATIONS_VIEW',
            'CUSTOMER_REGISTRATIONS_APPROVE',
            'PREMIUM_FEATURES_VIEW',
            'PREMIUM_FEATURES_EDIT',
            'REPORTS_VIEW',
            'NOTIFICATIONS_SEND',
        ];

        foreach ($viewOnly as $module) {
            $codes[] = strtoupper($module) . '_VIEW';
        }

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

        UserType::where('name', 'customer')->first()?->permissions()->sync(
            Permission::whereIn('code', [
                'ORDERS_VIEW',
                'ORDERS_CREATE',
                'CUSTOMER_BRANCHES_VIEW',
                'CUSTOMER_BRANCHES_CREATE',
                'CUSTOMER_BRANCHES_EDIT',
                'CUSTOMER_BRANCHES_DELETE',
                'INVENTORY_VIEW',
            ])->pluck('id')
        );

        UserType::where('name', 'delegate')->first()?->permissions()->sync(
            Permission::whereIn('code', ['ORDER_ASSIGN', 'ORDERS_VIEW'])->pluck('id')
        );
    }
}
