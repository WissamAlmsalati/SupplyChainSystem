<?php

namespace Database\Seeders;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\InsufficientStockException;
use App\Models\AppUser;
use App\Models\Notification;
use App\Models\ProductVariant;
use App\Services\OrderPlacementService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

// Orders across the last 6 months, placed through the real placement flow so
// snapshots, stock movements and status logs are all consistent.
class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $placement = app(OrderPlacementService::class);

        $customers = AppUser::with('addresses')
            ->whereHas('userType', fn ($q) => $q->where('name', UserRole::Customer->value))
            ->get()
            ->filter(fn ($c) => $c->addresses->isNotEmpty());
        $variants = ProductVariant::where('is_active', true)->get();

        if ($customers->isEmpty() || $variants->isEmpty()) {
            return;
        }

        // Status path an order walks through; the last step is where it stops.
        $paths = [
            [OrderStatus::Pending],
            [OrderStatus::Confirmed],
            [OrderStatus::Confirmed, OrderStatus::Preparing],
            [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::OutForDelivery],
            [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::OutForDelivery, OrderStatus::Delivered],
            [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::Received],
            [OrderStatus::Cancelled],
        ];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonthsNoOverflow($i);
            $count = [5 => 4, 4 => 8, 3 => 10, 2 => 12, 1 => 15, 0 => 6][$i];

            for ($j = 0; $j < $count; $j++) {
                $customer = $customers->random();
                $items = $variants->random(rand(1, 3))
                    ->map(fn ($v) => ['product_variant_id' => $v->id, 'quantity' => rand(1, 5)])
                    ->all();

                try {
                    $order = $placement->place($customer, $customer->addresses->random(), $items, fake()->randomElement(OrderSource::cases()));
                } catch (InsufficientStockException) {
                    continue;
                }

                $placedAt = $i === 0
                    ? Carbon::now()->subDays(rand(0, max(0, Carbon::now()->day - 1)))
                    : $month->copy()->day(rand(1, $month->daysInMonth));
                $order->update(['placed_at' => $placedAt->setTime(rand(8, 20), rand(0, 59))]);

                // Past months are mostly finished; the current month is mostly in progress.
                $path = $i > 0 && rand(1, 10) <= 7 ? $paths[5] : $paths[array_rand($paths)];
                foreach ($path as $status) {
                    $order->update(['status' => $status]);
                }
            }
        }

        // Placement notifies admins per order; keep the seeded inbox clean.
        Notification::query()->delete();
    }
}
