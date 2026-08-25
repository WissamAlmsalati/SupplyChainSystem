<?php

namespace Database\Seeders;

use App\Models\AppUser;
use App\Models\Cafe;
use App\Models\CafeBranch;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserType;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Self-contained demo data for the Bruno Cafe API collection.
 *
 * Run before executing the Bruno collection:
 *   php artisan db:seed --class=BrunoDemoSeeder
 */
class BrunoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $cafeType = UserType::firstOrCreate(['name' => 'cafe']);
        UserType::firstOrCreate(['name' => 'admin']);
        UserType::firstOrCreate(['name' => 'super_admin']);
        UserType::firstOrCreate(['name' => 'delegate']);

        $permissions = collect([
            'ORDERS_VIEW', 'ORDERS_EDIT', 'ORDERS_CREATE',
            'CAFE_BRANCHES_VIEW', 'CAFE_BRANCHES_CREATE', 'CAFE_BRANCHES_EDIT', 'CAFE_BRANCHES_DELETE',
            'INVENTORY_VIEW',
        ])->map(fn ($code) => Permission::firstOrCreate(['code' => $code]));
        $cafeType->permissions()->syncWithoutDetaching($permissions->pluck('id'));

        $cafe = Cafe::firstOrCreate(
            ['contact_info' => '0911111111'],
            [
                'name' => 'مقهى اختبار برونو',
                'is_active' => true,
            ]
        );

        $user = AppUser::firstOrCreate(
            ['mobile_number' => '0911111111'],
            [
                'name' => 'Cafe Owner',
                'email' => 'cafe@bruno.test',
                'password_hash' => Hash::make('password'),
                'user_type_id' => $cafeType->id,
                'cafe_id' => $cafe->id,
                'is_active' => true,
            ]
        );
        $user->update(['cafe_id' => $cafe->id]);

        $zone = DeliveryZone::firstOrCreate(
            ['hex_id' => '842da29ffffffff'],
            [
                'name' => 'منطقة اختبار',
                'delivery_price' => 5,
                'latitude' => 27.0,
                'longitude' => 17.0,
                'is_active' => true,
            ]
        );

        $branch = CafeBranch::firstOrCreate(
            [
                'cafe_id' => $cafe->id,
                'name' => 'فرع رئيسي',
            ],
            [
                'city' => 'طرابلس',
                'street' => 'الشارع الرئيسي',
                'latitude' => 27.0,
                'longitude' => 17.0,
                'delivery_zone_id' => $zone->id,
                'is_active' => true,
            ]
        );

        $warehouse = Warehouse::firstOrCreate(
            ['name' => 'مستودع اختبار'],
            [
                'city' => 'طرابلس',
                'latitude' => 27.0,
                'longitude' => 17.0,
            ]
        );

        $category = Category::firstOrCreate(['name' => 'تصنيف اختبار']);

        $product = Product::firstOrCreate(
            ['name' => 'منتج اختبار'],
            [
                'category_id' => $category->id,
                'description' => 'وصف المنتج',
            ]
        );

        $variant = ProductVariant::firstOrCreate(
            ['sku' => 'TEST-001'],
            [
                'product_id' => $product->id,
                'attribute_value' => 'افتراضي',
                'price' => 10,
                'is_active' => true,
            ]
        );

        Inventory::firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'product_variant_id' => $variant->id,
            ],
            ['quantity' => 100]
        );

        Order::firstOrCreate(
            ['order_number' => 'ORD-2026-00001'],
            [
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'total_amount' => 20,
                'order_date' => now(),
                'delivery_zone_id' => $zone->id,
            ]
        );
    }
}
