<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

// Order numbers carry the hour they were placed in, in the timezone the office
// works in, and count within that hour.
class OrderNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_number_carries_the_hour_and_counts_within_it(): void
    {
        // 12:05 UTC is 14:05 in Tripoli, and the number follows the office.
        Carbon::setTestNow('2026-09-18 12:05:00');
        $first = Order::factory()->create();
        $second = Order::factory()->create();

        $this->assertSame('ORD-2026-09-18-14-001', $first->order_number);
        $this->assertSame('ORD-2026-09-18-14-002', $second->order_number);

        // The next hour starts its own count.
        Carbon::setTestNow('2026-09-18 13:00:10');
        $this->assertSame('ORD-2026-09-18-15-001', Order::factory()->create()->order_number);

        Carbon::setTestNow();
    }

    public function test_the_number_follows_placed_at_not_the_wall_clock(): void
    {
        Carbon::setTestNow('2026-09-18 21:30:00');
        $order = Order::factory()->create(['placed_at' => '2026-09-18 07:15:00']);

        $this->assertSame('ORD-2026-09-18-09-001', $order->order_number);
        Carbon::setTestNow();
    }

    public function test_numbers_stay_unique_and_fit_the_column(): void
    {
        Carbon::setTestNow('2026-09-18 12:00:00');
        $numbers = collect(range(1, 12))->map(fn () => Order::factory()->create()->order_number);

        $this->assertCount(12, $numbers->unique());
        $this->assertSame('ORD-2026-09-18-14-012', $numbers->last());
        $numbers->each(fn ($n) => $this->assertLessThanOrEqual(30, strlen($n)));
        Carbon::setTestNow();
    }

    public function test_an_explicit_number_is_kept(): void
    {
        $this->assertSame('ORD-LEGACY-1', Order::factory()->create(['order_number' => 'ORD-LEGACY-1'])->order_number);
    }
}
