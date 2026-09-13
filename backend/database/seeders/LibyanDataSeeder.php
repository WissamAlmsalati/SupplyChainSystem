<?php

namespace Database\Seeders;

use App\Enums\CartType;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\Cart;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserType;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// Catalog, warehouses (stocked through goods-in movements), customers with
// addresses and a recurring cart, and delegates.
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
        'قهوة',
        'شاي',
        'مستلزمات التحضير',
        'حلويات',
        'أكواب وتغليف',
    ];

    private array $warehouses = [
        'مستودع طرابلس الرئيسي',
        'مستودع بنغازي',
        'مستودع مصراتة',
    ];

    // Each product is sold in the listed sizes (variants) at the listed prices.
    private array $products = [
        ['name' => 'بن عربي محمص', 'brand' => 'الريف', 'category' => 'قهوة', 'sizes' => ['250 جم' => 12.0, '500 جم' => 22.0, '1 كجم' => 40.0]],
        ['name' => 'بن إسبريسو', 'brand' => 'لافاتزا', 'category' => 'قهوة', 'sizes' => ['500 جم' => 35.0, '1 كجم' => 65.0]],
        ['name' => 'قهوة تركية', 'brand' => 'محمد أفندي', 'category' => 'قهوة', 'sizes' => ['250 جم' => 15.0, '500 جم' => 28.0]],
        ['name' => 'شاي أخضر', 'brand' => 'ليبتون', 'category' => 'شاي', 'sizes' => ['25 كيس' => 6.0, '100 كيس' => 20.0]],
        ['name' => 'شاي أحمر', 'brand' => 'الربيع', 'category' => 'شاي', 'sizes' => ['400 جم' => 9.0, '1 كجم' => 21.0]],
        ['name' => 'سكر أبيض', 'brand' => null, 'category' => 'مستلزمات التحضير', 'sizes' => ['1 كجم' => 4.0, '5 كجم' => 18.0]],
        ['name' => 'حليب مبخر', 'brand' => 'نيدو', 'category' => 'مستلزمات التحضير', 'sizes' => ['400 جم' => 11.0, '900 جم' => 23.0]],
        ['name' => 'شراب فانيليا', 'brand' => 'مونين', 'category' => 'مستلزمات التحضير', 'sizes' => ['700 مل' => 30.0]],
        ['name' => 'كرواسان مجمد', 'brand' => null, 'category' => 'حلويات', 'sizes' => ['12 قطعة' => 25.0, '24 قطعة' => 46.0]],
        ['name' => 'كيكة الشوكولاتة', 'brand' => null, 'category' => 'حلويات', 'sizes' => ['قالب' => 45.0]],
        ['name' => 'أكواب ورقية', 'brand' => null, 'category' => 'أكواب وتغليف', 'sizes' => ['8 أونصة × 50' => 8.0, '12 أونصة × 50' => 10.0]],
        ['name' => 'أغطية أكواب', 'brand' => null, 'category' => 'أكواب وتغليف', 'sizes' => ['× 100' => 7.0]],
    ];

    public function run(): void
    {
        $stock = app(StockService::class);

        $warehouseRecords = collect($this->warehouses)->map(fn ($name, $i) => Warehouse::create([
            'name' => $name,
            'city' => $this->cities[$i]['name'],
            'latitude' => $this->cities[$i]['lat'],
            'longitude' => $this->cities[$i]['lng'],
        ]));

        $zoneRecords = [];
        foreach ($this->cities as $i => $city) {
            $zoneRecords[$city['name']] = DeliveryZone::create([
                'warehouse_id' => $warehouseRecords[$i % $warehouseRecords->count()]->id,
                'hex_id' => 'libya_' . str_replace(' ', '_', $city['name']),
                'name' => 'منطقة ' . $city['name'],
                'delivery_price' => fake()->randomElement([3.00, 4.00, 5.00, 6.00]),
                'latitude' => $city['lat'],
                'longitude' => $city['lng'],
                'is_active' => true,
            ]);
        }

        $categoryRecords = collect($this->categories)->mapWithKeys(fn ($name) => [$name => Category::create(['name' => $name])]);

        $variants = collect();
        foreach ($this->products as $i => $data) {
            $product = Product::create([
                'category_id' => $categoryRecords[$data['category']]->id,
                'name' => $data['name'],
                'brand' => $data['brand'],
                'description' => $data['name'] . ' بجودة عالية لتوريد المقاهي',
                'is_active' => true,
            ]);

            $s = 0;
            foreach ($data['sizes'] as $size => $price) {
                $variants->push(ProductVariant::create([
                    'product_id' => $product->id,
                    'name' => $size,
                    'sku' => sprintf('PRD-%04d-%02d', $i + 1, ++$s),
                    'price' => $price,
                    'cost_price' => round($price * 0.7, 2),
                    'is_active' => true,
                ]));
            }
        }

        // Opening stock received into every warehouse.
        foreach ($warehouseRecords as $warehouse) {
            foreach ($variants as $variant) {
                $stock->receive($warehouse->id, $variant->id, fake()->numberBetween(40, 200), [
                    'unit_cost' => $variant->cost_price,
                    'expiry_date' => now()->addMonths(fake()->numberBetween(3, 18))->toDateString(),
                ], 'رصيد افتتاحي');
            }
        }

        // Curated rows for the customer app home screen.
        $sections = [
            'الأكثر طلباً' => ['بن عربي محمص', 'بن إسبريسو', 'حليب مبخر', 'أكواب ورقية'],
            'مستلزمات التحضير' => ['سكر أبيض', 'شراب فانيليا', 'أغطية أكواب'],
        ];
        foreach (array_keys($sections) as $i => $title) {
            $section = \App\Models\FeaturedSection::create(['title' => $title, 'sort_order' => $i]);
            $section->syncProducts(Product::whereIn('name', $sections[$title])->get()
                ->sortBy(fn ($p) => array_search($p->name, $sections[$title]))->pluck('id')->all());
        }

        $customerType = UserType::where('name', UserRole::Customer->value)->firstOrFail();
        foreach ($this->cafeNames as $i => $cafeName) {
            $city = $this->cities[$i];
            $secondaryCity = $this->cities[($i + 1) % count($this->cities)];

            $customer = AppUser::create([
                'name' => $cafeName,
                'email' => 'cafe' . ($i + 1) . '@example.com',
                'mobile_number' => '091000000' . ($i + 1),
                'user_type_id' => $customerType->id,
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);
            $customer->customerProfile->update([
                'business_name' => $cafeName,
                'latitude' => $city['lat'],
                'longitude' => $city['lng'],
            ]);

            Address::create([
                'user_id' => $customer->id,
                'name' => 'فرع ' . $city['name'] . ' الرئيسي',
                'city' => $city['name'],
                'street' => 'شارع الجمهورية',
                'contact_phones' => [$customer->mobile_number],
                'latitude' => $city['lat'],
                'longitude' => $city['lng'],
                'delivery_zone_id' => $zoneRecords[$city['name']]->id,
                'is_default' => true,
            ]);
            Address::create([
                'user_id' => $customer->id,
                'name' => 'فرع ' . $secondaryCity['name'],
                'city' => $secondaryCity['name'],
                'street' => 'شارع عمر المختار',
                'latitude' => $secondaryCity['lat'] + 0.002,
                'longitude' => $secondaryCity['lng'] + 0.002,
                'delivery_zone_id' => $zoneRecords[$secondaryCity['name']]->id,
            ]);

            $recurring = Cart::create(['user_id' => $customer->id, 'type' => CartType::Recurring, 'name' => 'الطلبية الأسبوعية']);
            $recurring->items()->createMany(
                $variants->random(3)->map(fn ($v) => ['product_variant_id' => $v->id, 'quantity' => fake()->numberBetween(2, 6)])->all()
            );
        }

        $delegateType = UserType::where('name', UserRole::Delegate->value)->firstOrFail();
        foreach (array_slice($this->cities, 0, 3) as $i => $city) {
            $delegate = AppUser::create([
                'name' => 'مندوب ' . $city['name'],
                'email' => 'delegate' . ($i + 1) . '@example.com',
                'mobile_number' => '092000000' . ($i + 1),
                'user_type_id' => $delegateType->id,
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);
            $delegate->delegateProfile->update([
                'is_available' => true,
                'latitude' => $city['lat'] + 0.01,
                'longitude' => $city['lng'] + 0.01,
                'location_updated_at' => now(),
            ]);
        }
    }
}
