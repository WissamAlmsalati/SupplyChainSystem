<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Order;
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

// Days are the office's days (Tripoli, UTC+2), while storage stays UTC.
class BusinessTimeTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.AppUser::factory()->admin()->create()->createToken('t')->plainTextToken];
    }

    public function test_an_order_placed_just_after_local_midnight_belongs_to_the_new_day(): void
    {
        // 00:30 on the 10th in Tripoli is 22:30 on the 9th in UTC.
        $lateNight = Order::factory()->create(['status' => 'delivered', 'total_amount' => 100, 'subtotal' => 95, 'delivery_fee' => 5, 'placed_at' => Carbon::parse('2026-03-09 22:30:00', 'UTC')]);
        Order::factory()->create(['status' => 'delivered', 'total_amount' => 40, 'subtotal' => 35, 'delivery_fee' => 5, 'placed_at' => Carbon::parse('2026-03-09 12:00:00', 'UTC')]);

        $tenth = $this->getJson('/api/v1/reports/sales?from=2026-03-10&to=2026-03-10', $this->headers())->assertOk()->json();
        $this->assertSame(1, $tenth['summary']['orders']);
        $this->assertEquals(100, $tenth['summary']['revenue']);
        $this->assertSame('2026-03-10', $tenth['series'][0]['bucket']);
        $this->assertSame(['from' => '2026-03-10', 'to' => '2026-03-10'], $tenth['period']);

        $ninth = $this->getJson('/api/v1/reports/sales?from=2026-03-09&to=2026-03-09', $this->headers())->assertOk()->json();
        $this->assertEquals(40, $ninth['summary']['revenue']);

        // The dashboard's order filter means the same days.
        $this->getJson('/api/v1/orders?date_from=2026-03-10&date_to=2026-03-10', $this->headers())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $lateNight->id);
        $this->getJson('/api/v1/orders?date_from=not-a-date', $this->headers())->assertUnprocessable();
    }

    public function test_printed_times_agree_with_the_hour_in_the_order_number(): void
    {
        $at = Carbon::parse('2026-03-09 22:30:00', 'UTC');

        $this->assertSame('2026-03-10 00:30', BusinessTime::format($at));
        $this->assertStringStartsWith('ORD-2026-03-10-00-', Order::generateOrderNumber($at));
        $this->assertSame('2026-03-09 22:00:00', BusinessTime::dayStart('2026-03-10')->format('Y-m-d H:i:s'));
    }
}
