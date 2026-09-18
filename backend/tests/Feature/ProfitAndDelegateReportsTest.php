<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Category;
use App\Models\DelegateSettlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitAndDelegateReportsTest extends TestCase
{
    use RefreshDatabase;

    private AppUser $admin;

    private AppUser $customer;

    private AppUser $delegate;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = AppUser::factory()->admin()->create();
        $this->customer = AppUser::factory()->customer()->create();
        $this->delegate = AppUser::factory()->delegate()->create();
        $product = Product::create(['category_id' => Category::create(['name' => 'قهوة'])->id, 'name' => 'بن عربي']);
        $this->variant = ProductVariant::create(['product_id' => $product->id, 'name' => '500 جم', 'price' => 20, 'cost_price' => 12]);
    }

    private function headers(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->admin->createToken('t')->plainTextToken];
    }

    private function order(string $status, int $quantity, ?float $cost = 12, array $extra = []): array
    {
        $order = Order::factory()->create($extra + [
            'user_id' => $this->customer->id, 'delegate_id' => $this->delegate->id, 'status' => $status,
            'subtotal' => $quantity * 20, 'delivery_fee' => 5, 'total_amount' => $quantity * 20 + 5, 'delivery_city' => 'طرابلس',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_variant_id' => $this->variant->id, 'product_name' => 'بن عربي',
            'variant_name' => '500 جم', 'quantity' => $quantity, 'unit_price' => 20, 'unit_cost' => $cost,
        ]);

        return [$order, $item];
    }

    private function returned(Order $order, OrderItem $item, int $quantity, string $condition): void
    {
        $return = OrderReturn::create(['order_id' => $order->id, 'reason' => 'اختبار', 'total_value' => $quantity * 20]);
        $return->items()->create(['order_item_id' => $item->id, 'quantity' => $quantity, 'unit_price' => 20, 'condition' => $condition]);
    }

    public function test_profit_counts_returns_and_keeps_the_cost_of_damaged_goods(): void
    {
        // 10 sold. 2 came back sellable, 1 came back damaged.
        [$order, $item] = $this->order('delivered', 10);
        $this->returned($order, $item, 2, 'restock');
        $this->returned($order, $item, 1, 'damaged');
        $this->order('cancelled', 50);

        $summary = $this->getJson('/api/v1/reports/profit', $this->headers())->assertOk()->json('summary');

        // Revenue: 7 units kept x 20. Cost: 8 units gone for good x 12.
        $this->assertEquals(7, $summary['units']);
        $this->assertEquals(140, $summary['revenue']);
        $this->assertEquals(96, $summary['cost']);
        $this->assertEquals(44, $summary['gross_profit']);
        $this->assertEquals(31.4, $summary['margin_pct']);
        $this->assertEquals(60, $summary['returned_value']);
        $this->assertEquals(12, $summary['damaged_loss']);
        $this->assertEquals(5, $summary['delivery_fees']);
    }

    public function test_a_line_without_a_cost_is_reported_not_counted_as_pure_profit(): void
    {
        $this->order('delivered', 5);
        $this->order('delivered', 5, cost: null);

        $data = $this->getJson('/api/v1/reports/profit', $this->headers())->assertOk()->json();

        $this->assertEquals(200, $data['summary']['revenue']);
        $this->assertEquals(100, $data['summary']['uncosted_revenue']);
        $this->assertEquals(1, $data['summary']['uncosted_lines']);
        // 100 costed revenue against 60 cost, not 200 against 60.
        $this->assertEquals(40, $data['summary']['gross_profit']);
        $this->assertEquals(40.0, $data['summary']['margin_pct']);
        $this->assertFalse($data['by_product'][0]['cost_known']);
        $this->assertSame('قهوة', $data['by_category'][0]['name']);
        $this->assertSame('طرابلس', $data['by_city'][0]['name']);
    }

    public function test_new_orders_keep_the_cost_they_were_sold_at(): void
    {
        $this->assertContains('unit_cost', (new OrderItem)->getFillable());
        [, $item] = $this->order('delivered', 1);
        $this->variant->update(['cost_price' => 99]);

        $this->assertSame('12.00', $item->fresh()->unit_cost);
    }

    public function test_delegate_performance_reads_times_from_the_status_log(): void
    {
        $placed = now()->subHours(3);
        [$fast] = $this->order('delivered', 1, extra: ['placed_at' => $placed]);
        [$received] = $this->order('received', 1, extra: ['placed_at' => $placed]);
        $this->order('cancelled', 1);
        $this->order('out_for_delivery', 1);

        foreach ([[$fast, 30], [$received, 50]] as [$order, $minutes]) {
            OrderStatusLog::where('order_id', $order->id)->delete();
            foreach ([['out_for_delivery', $placed->copy()->addMinutes(60)], ['delivered', $placed->copy()->addMinutes(60 + $minutes)]] as [$status, $at]) {
                $log = new OrderStatusLog(['order_id' => $order->id, 'to_status' => $status]);
                $log->created_at = $at;
                $log->save();
            }
        }

        Payment::create(['order_id' => $fast->id, 'amount' => 25, 'method' => 'cash', 'status' => 'paid', 'paid_at' => now(), 'collected_by' => $this->delegate->id]);
        $this->delegate->delegateProfile()->updateOrCreate([], ['custody_balance' => 25]);
        $settlement = new DelegateSettlement(['reference_number' => 'SET-1', 'delegate_id' => $this->delegate->id, 'amount' => 10, 'custody_before' => 10, 'custody_after' => 0]);
        $settlement->created_at = now()->subDays(4);
        $settlement->save();

        $data = $this->getJson('/api/v1/reports/delegates', $this->headers())->assertOk()->json();
        $row = collect($data['delegates'])->firstWhere('id', $this->delegate->id);

        $this->assertSame(4, $row['assigned']);
        $this->assertSame(2, $row['delivered']);
        $this->assertSame(1, $row['cancelled']);
        $this->assertSame(1, $row['in_progress']);
        // 2 delivered of 3 finished; the one still on the road does not count.
        $this->assertEquals(66.7, $row['success_rate']);
        $this->assertSame(40, $row['avg_delivery_minutes']);
        $this->assertSame(100, $row['avg_total_minutes']);
        $this->assertEquals(25, $row['cash_collected']);
        $this->assertEquals(25, $row['custody_balance']);
        $this->assertSame(4, $row['days_since_settlement']);
        $this->assertEquals(66.7, $data['summary']['success_rate']);
    }

    public function test_both_reports_download_as_pdf_and_excel_and_need_the_reports_code(): void
    {
        $this->order('delivered', 3);

        foreach (['profit', 'delegates'] as $report) {
            $this->get("/api/v1/reports/{$report}?format=pdf", $this->headers())->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->get("/api/v1/reports/{$report}?format=xlsx", $this->headers())->assertOk();
        }

        $this->app['auth']->forgetGuards();
        $token = $this->customer->createToken('t')->plainTextToken;
        $this->getJson('/api/v1/reports/profit', ['Authorization' => 'Bearer '.$token])->assertForbidden();
    }
}
