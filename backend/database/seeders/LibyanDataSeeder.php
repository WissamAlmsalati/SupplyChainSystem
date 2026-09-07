<?php

namespace Database\Seeders;

use App\Models\AppUser;
use App\Models\Cafe;
use App\Models\CafeBranch;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserType;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LibyanDataSeeder extends Seeder
{
    private array $cities = [
        ['name' => 'طرابلس', 'lat' => 32.8872, 'lng' => 13.1913],
        ['name' => 'بنغازي', 'lat' => 32.1167, 'lng' => 20.0667],
        ['name' => 'مصراتة', 'lat' => 32.3754, 'lng' => 15.0925],
        ['name' => 'الزاوية', 'lat' => 32.7522, 'lng' => 12.7278],
        ['name' => 'سبها', 'lat' => 27.0377, 'lng' => 14.4283],
        ['name' => 'البيضاء', 'lat' => 32.7667, 'lng' => 21.7333],
        ['name' => 'درنة', 'lat' => 32.7649, 'lng' => 22.6391],
        ['name' => 'اجدابيا', 'lat' => 30.7554, 'lng' => 20.2263],
    ];

    private array $cafeNames = [
        'مقهى طرابلس',
        'مقهى بنغازي',
        'مقهى مصراتة',
        'مقهى الزاوية',
        'مقهى سبها',
    ];

    private array $categories = [
        'مشروبات ساخنة',
        'مشروبات باردة',
        'حلويات',
        'ساندويتشات',
        'وجبات خفيفة',
    ];

    private array $warehouses = [
        'مستودع طرابلس الرئيسي',
        'مستودع بنغازي',
        'مستودع مصراتة',
    ];

    private array $products = [
        ['name' => 'قهوة تركية', 'category' => 'مشروبات ساخنة', 'price' => 4.00, 'attr' => 'حجم'],
        ['name' => 'كابتشينو', 'category' => 'مشروبات ساخنة', 'price' => 5.50, 'attr' => 'حجم'],
        ['name' => 'شاي أخضر', 'category' => 'مشروبات ساخنة', 'price' => 3.00, 'attr' => 'حجم'],
        ['name' => 'عصير برتقال طازج', 'category' => 'مشروبات باردة', 'price' => 6.00, 'attr' => 'حجم'],
        ['name' => 'ليموناضة', 'category' => 'مشروبات باردة', 'price' => 5.00, 'attr' => 'حجم'],
        ['name' => 'ميلك شيك فراولة', 'category' => 'مشروبات باردة', 'price' => 8.00, 'attr' => 'حجم'],
        ['name' => 'كيكة الشوكولاتة', 'category' => 'حلويات', 'price' => 7.00, 'attr' => 'قطعة'],
        ['name' => 'كرواسان', 'category' => 'حلويات', 'price' => 4.50, 'attr' => 'حجم'],
        ['name' => 'ساندويتش دجاج', 'category' => 'ساندويتشات', 'price' => 9.00, 'attr' => 'حجم'],
        ['name' => 'ساندويتش تونة', 'category' => 'ساندويتشات', 'price' => 8.00, 'attr' => 'حجم'],
        ['name' => 'بطاطس مقلي', 'category' => 'وجبات خفيفة', 'price' => 4.00, 'attr' => 'حجم'],
        ['name' => 'ناجتس دجاج', 'category' => 'وجبات خفيفة', 'price' => 6.50, 'attr' => 'قطع'],
    ];

    public function run(): void
    {
        $adminType = UserType::where('name', 'admin')->first();
        $cafeType = UserType::where('name', 'cafe')->first();

        $admin = AppUser::factory()->create([
            'name' => 'مدير النظام',
            'email' => 'admin@example.com',
            'user_type_id' => $adminType?->id,
            'password_hash' => Hash::make('password'),
        ]);

        // Warehouses
        $warehouseRecords = [];
        foreach ($this->warehouses as $i => $name) {
            $city = $this->cities[$i];
            $warehouseRecords[] = Warehouse::create([
                'name' => $name,
                'city' => $city['name'],
                'latitude' => $city['lat'],
                'longitude' => $city['lng'],
            ]);
        }

        // Delivery zones
        $zoneRecords = [];
        foreach ($this->cities as $city) {
            $zoneRecords[$city['name']] = DeliveryZone::create([
                'hex_id' => 'libya_' . strtolower(str_replace(' ', '_', $city['name'])),
                'name' => 'منطقة ' . $city['name'],
                'delivery_price' => fake()->randomElement([3.00, 4.00, 5.00, 6.00]),
                'latitude' => $city['lat'],
                'longitude' => $city['lng'],
                'is_active' => true,
            ]);
        }

        // Categories
        $categoryRecords = [];
        foreach ($this->categories as $name) {
            $categoryRecords[$name] = Category::create(['name' => $name]);
        }

        // Products and variants
        $variantRecords = [];
        foreach ($this->products as $i => $productData) {
            $product = Product::create([
                'name' => $productData['name'],
                'description' => $productData['name'] . ' من أفضل المنتجات',
                'category_id' => $categoryRecords[$productData['category']]->id,
            ]);

            foreach (['صغير', 'كبير'] as $size) {
                $variantRecords[] = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => 'PRD-' . ($i + 1) . '-' . ($size === 'كبير' ? 'L' : 'S'),
                    'attribute_name' => $productData['attr'],
                    'attribute_value' => $size,
                    'price' => $size === 'كبير' ? $productData['price'] + 2 : $productData['price'],
                    'is_active' => true,
                ]);
            }
        }

        // Inventory
        foreach ($variantRecords as $variant) {
            foreach ($warehouseRecords as $warehouse) {
                Inventory::create([
                    'warehouse_id' => $warehouse->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => fake()->numberBetween(5, 100),
                ]);
            }
        }

        // Cafes, branches and cafe users
        $cafeRecords = [];
        foreach ($this->cafeNames as $i => $cafeName) {
            $city = $this->cities[$i % count($this->cities)];
            $cafe = Cafe::create([
                'name' => $cafeName,
                'contact_info' => '021234567' . $i,
                'is_active' => true,
                'created_by_admin_id' => $admin->id,
            ]);
            $cafeRecords[] = $cafe;

            AppUser::factory()->create([
                'name' => 'مدير ' . $cafeName,
                'email' => 'cafe' . ($i + 1) . '@example.com',
                'user_type_id' => $cafeType?->id,
                'password_hash' => Hash::make('password'),
            ])->syncCafeUser(['cafe_id' => $cafe->id]);

            // Main branch
            CafeBranch::create([
                'cafe_id' => $cafe->id,
                'name' => 'فرع ' . $city['name'] . ' الرئيسي',
                'city' => $city['name'],
                'street' => 'شارع الجمهورية',
                'latitude' => $city['lat'],
                'longitude' => $city['lng'],
                'delivery_zone_id' => $zoneRecords[$city['name']]?->id,
                'is_active' => true,
            ]);

            // Secondary branch in another city
            $secondaryCity = $this->cities[($i + 1) % count($this->cities)];
            CafeBranch::create([
                'cafe_id' => $cafe->id,
                'name' => 'فرع ' . $secondaryCity['name'],
                'city' => $secondaryCity['name'],
                'street' => 'شارع عمر المختار',
                'latitude' => $secondaryCity['lat'] + 0.002,
                'longitude' => $secondaryCity['lng'] + 0.002,
                'delivery_zone_id' => $zoneRecords[$secondaryCity['name']]?->id,
                'is_active' => true,
            ]);
        }

        // Orders
        $branches = CafeBranch::all();
        $cafeUsers = AppUser::where('user_type_id', $cafeType?->id)->get();

        foreach ($cafeUsers as $user) {
            $branch = $branches->where('cafe_id', $user->cafe_id)->first();
            if (! $branch) {
                continue;
            }

            $itemsCount = fake()->numberBetween(1, 3);
            $items = [];
            $total = 0;

            for ($k = 0; $k < $itemsCount; $k++) {
                $variant = $variantRecords[array_rand($variantRecords)];
                $qty = fake()->numberBetween(1, 4);
                $price = (float) $variant->price;
                $total += $price * $qty;
                $items[] = [
                    'product_variant_id' => $variant->id,
                    'quantity' => $qty,
                    'unit_price' => $price,
                ];
            }

            $deliveryFee = (float) ($branch->deliveryZone?->delivery_price ?? 0);
            $total += $deliveryFee;

            $order = Order::create([
                'user_id' => $user->id,
                'branch_id' => $branch->id,
                'delegate_id' => null,
                'delivery_zone_id' => $branch->delivery_zone_id,
                'delivery_fee' => $deliveryFee,
                'order_date' => now()->subDays(fake()->numberBetween(0, 60))->toDateTimeString(),
                'status' => fake()->randomElement(['pending', 'processing', 'completed', 'cancelled']),
                'source' => fake()->randomElement(['app', 'add order from dashboard']),
                'total_amount' => $total,
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $item['product_variant_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }
        }
    }
}
