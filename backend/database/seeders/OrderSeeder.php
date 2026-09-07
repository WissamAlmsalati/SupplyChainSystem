<?php

namespace Database\Seeders;

use App\Models\AppUser;
use App\Models\Cafe;
use App\Models\CafeBranch;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure a cafe user type exists
        if (UserType::count() === 0) {
            UserType::firstOrCreate(['name' => 'cafe']);
        }

        $cafeType = UserType::where('name', 'cafe')->firstOrFail();

        // Delivery zones
        if (DeliveryZone::count() === 0) {
            DeliveryZone::factory()->count(5)->create();
        }
        $zones = DeliveryZone::all();

        // Cafes + branches
        if (Cafe::count() === 0) {
            Cafe::factory()->count(3)->create();
        }

        if (CafeBranch::count() === 0) {
            Cafe::all()->each(function (Cafe $cafe) use ($zones) {
                CafeBranch::factory()->count(rand(2, 3))->create([
                    'cafe_id' => $cafe->id,
                    'delivery_zone_id' => $zones->random()->id,
                ]);
            });
        }
        $branches = CafeBranch::all();

        // Cafe users
        if (AppUser::where('user_type_id', $cafeType->id)->count() === 0) {
            Cafe::all()->each(function (Cafe $cafe) use ($cafeType) {
                AppUser::factory()->count(rand(1, 2))->create([
                    'user_type_id' => $cafeType->id,
                    'password_hash' => Hash::make('password'),
                ])->each(fn (AppUser $user) => $user->syncCafeUser(['cafe_id' => $cafe->id]));
            });
        }
        $users = AppUser::where('user_type_id', $cafeType->id)->get();

        // Products + variants
        if (ProductVariant::count() === 0) {
            if (Category::count() === 0) {
                Category::factory()->count(5)->create();
            }
            $categories = Category::all();

            Product::factory()->count(15)->create([
                'category_id' => fn () => $categories->random()->id,
            ])->each(function (Product $product) {
                ProductVariant::factory()->count(rand(1, 3))->create([
                    'product_id' => $product->id,
                ]);
            });
        }
        $variants = ProductVariant::all();

        $statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];

        // Make this seeder rerunnable by removing previously seeded orders first
        Order::where('source', 'seed')
            ->where('order_date', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->delete();

        // Seed orders across the last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonthsNoOverflow($i);
            $daysInMonth = $month->daysInMonth;
            // Vary order volume per month so the chart looks realistic
            $ordersCount = match ($i) {
                5 => rand(3, 6),   // oldest month
                4 => rand(8, 14),
                3 => rand(10, 18),
                2 => rand(12, 22),
                1 => rand(15, 25), // recent growth
                0 => rand(5, 12),  // current month (partial)
                default => rand(8, 15),
            };

            for ($j = 0; $j < $ordersCount; $j++) {
                $day = rand(1, $daysInMonth);
                $orderDate = $month->copy()->day($day);
                $status = $statuses[array_rand($statuses)];
                $deliveryFee = (float) fake()->randomFloat(2, 0, 30);

                $items = [];
                $itemsTotal = 0;
                $itemCount = rand(1, 3);

                for ($k = 0; $k < $itemCount; $k++) {
                    $variant = $variants->random();
                    $quantity = rand(1, 5);
                    $unitPrice = (float) ($variant->price ?: fake()->randomFloat(2, 5, 100));

                    $items[] = [
                        'product_variant_id' => $variant->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                    ];
                    $itemsTotal += $quantity * $unitPrice;
                }

                $order = Order::create([
                    'user_id' => $users->random()->id,
                    'branch_id' => $branches->random()->id,
                    'delegate_id' => null,
                    'delivery_zone_id' => $zones->random()->id,
                    'delivery_fee' => $deliveryFee,
                    'order_date' => $orderDate,
                    'status' => $status,
                    'source' => 'seed',
                    'total_amount' => round($itemsTotal + $deliveryFee, 2),
                    'order_number' => Order::generateOrderNumber(),
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
}
