<?php

namespace Database\Seeders;

use App\Enums\CartType;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\Cart;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserType;
use App\Models\Warehouse;
use App\Services\StockService;
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
        $customerType = UserType::firstOrCreate(['name' => UserRole::Customer->value]);
        foreach ([UserRole::Admin, UserRole::SuperAdmin, UserRole::Delegate] as $role) {
            UserType::firstOrCreate(['name' => $role->value]);
        }

        $permissions = collect([
            'ORDERS_VIEW', 'ORDERS_CREATE',
            'CAFE_BRANCHES_VIEW', 'CAFE_BRANCHES_CREATE', 'CAFE_BRANCHES_EDIT', 'CAFE_BRANCHES_DELETE',
            'INVENTORY_VIEW',
        ])->map(fn ($code) => Permission::firstOrCreate(['code' => $code]));
        $customerType->permissions()->syncWithoutDetaching($permissions->pluck('id'));

        $user = AppUser::firstOrCreate(
            ['mobile_number' => '0911111111'],
            [
                'name' => 'Cafe Owner',
                'email' => 'cafe@bruno.test',
                'password' => Hash::make('password'),
                'user_type_id' => $customerType->id,
                'is_active' => true,
            ]
        );
        $user->customerProfile()->updateOrCreate(['user_id' => $user->id], [
            'business_name' => 'Bruno Cafe',
            'latitude' => 27.0,
            'longitude' => 17.0,
        ]);

        $zone = DeliveryZone::firstOrCreate(
            ['hex_id' => '842da29ffffffff'],
            ['name' => 'منطقة اختبار', 'delivery_price' => 5, 'latitude' => 27.0, 'longitude' => 17.0, 'is_active' => true]
        );

        Address::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'فرع رئيسي'],
            [
                'city' => 'طرابلس',
                'street' => 'الشارع الرئيسي',
                'latitude' => 27.0,
                'longitude' => 17.0,
                'delivery_zone_id' => $zone->id,
                'is_default' => true,
            ]
        );

        $warehouse = Warehouse::firstOrCreate(['name' => 'مستودع اختبار'], ['city' => 'طرابلس', 'latitude' => 27.0, 'longitude' => 17.0]);
        $category = Category::firstOrCreate(['name' => 'تصنيف اختبار']);
        $product = Product::firstOrCreate(['name' => 'منتج اختبار'], ['category_id' => $category->id, 'description' => 'وصف المنتج']);
        $variant = ProductVariant::firstOrCreate(
            ['sku' => 'TEST-001'],
            ['product_id' => $product->id, 'name' => 'افتراضي', 'price' => 10, 'is_active' => true]
        );

        $onHand = (int) $variant->inventories()->where('warehouse_id', $warehouse->id)->value('quantity');
        if ($onHand < 100) {
            app(StockService::class)->adjust($warehouse->id, $variant->id, 100 - $onHand, StockMovementType::Adjustment, null, 'Bruno demo stock');
        }

        $recurring = Cart::firstOrCreate(['user_id' => $user->id, 'type' => CartType::Recurring, 'name' => 'الطلبية الأسبوعية']);
        $recurring->items()->updateOrCreate(['product_variant_id' => $variant->id], ['quantity' => 2]);
    }
}
