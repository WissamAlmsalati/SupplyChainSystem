<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\CustodyEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserType;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
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
        $this->variant = ProductVariant::create(['product_id' => $product->id, 'name' => '500 جم', 'price' => 20]);
    }

    private function as(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    private function order(string $status = 'delivered', float $total = 100, ?string $placedAt = null): Order
    {
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'delegate_id' => $this->delegate->id,
            'status' => $status,
            'subtotal' => $total - 5,
            'delivery_fee' => 5,
            'total_amount' => $total,
            'placed_at' => $placedAt ?? now(),
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_variant_id' => $this->variant->id, 'product_name' => 'بن عربي', 'variant_name' => '500 جم', 'quantity' => 2, 'unit_price' => ($total - 5) / 2]);

        return $order;
    }

    public function test_sales_report_counts_only_uncancelled_orders(): void
    {
        $this->order('delivered', 100);
        $this->order('confirmed', 50);
        $this->order('cancelled', 999);
        $this->order('delivered', 70, now()->subDays(60)->toDateTimeString());

        $res = $this->getJson('/api/v1/reports/sales', $this->as($this->admin))->assertOk();
        $res->assertJsonPath('summary.orders', 2)
            ->assertJsonPath('summary.cancelled', 1)
            ->assertJsonPath('summary.revenue', 150)
            ->assertJsonPath('summary.items_sold', 4)
            ->assertJsonPath('top_products.0.product', 'بن عربي')
            ->assertJsonPath('by_delegate.0.orders', 2);
        $this->assertCount(1, $res->json('series'));
    }

    public function test_sales_report_accepts_a_period_and_monthly_grouping(): void
    {
        $this->order('delivered', 70, '2026-01-15 10:00:00');
        $this->order('delivered', 30, '2026-02-01 10:00:00');

        $this->getJson('/api/v1/reports/sales?from=2026-01-01&to=2026-02-28&group_by=month', $this->as($this->admin))
            ->assertOk()
            ->assertJsonPath('summary.revenue', 100)
            ->assertJsonPath('series.0.bucket', '2026-01')
            ->assertJsonPath('series.1.bucket', '2026-02');

        $this->getJson('/api/v1/reports/sales?from=2026-02-01&to=2026-01-01', $this->as($this->admin))->assertUnprocessable();
    }

    public function test_reports_download_as_pdf_and_excel(): void
    {
        $this->order();

        $pdf = $this->get('/api/v1/reports/sales?format=pdf', $this->as($this->admin))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->get('/api/v1/reports/sales?format=xlsx', $this->as($this->admin))->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $xlsx->headers->get('Content-Disposition'));

        $this->get('/api/v1/reports/orders', $this->as($this->admin))->assertOk();
        $this->get('/api/v1/reports/inventory?format=pdf', $this->as($this->admin))->assertOk();
        $this->getJson('/api/v1/reports/sales?format=docx', $this->as($this->admin))->assertUnprocessable();
    }

    public function test_invoice_pdf_for_dashboard_and_for_the_customers_own_order(): void
    {
        $order = $this->order();
        $other = Order::factory()->create();

        $res = $this->get("/api/v1/orders/{$order->id}/invoice", $this->as($this->admin))->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString($order->order_number, $res->headers->get('Content-Disposition'));

        $this->get("/api/v1/customer/orders/{$order->id}/invoice", $this->as($this->customer))->assertOk();
        $this->get("/api/v1/customer/orders/{$other->id}/invoice", $this->as($this->customer))->assertNotFound();
    }

    public function test_custody_statement_has_opening_and_closing_balances(): void
    {
        $mk = fn (float $amount, float $after, string $at, string $type = 'order_collection') => CustodyEntry::forceCreate([
            'delegate_id' => $this->delegate->id, 'type' => $type, 'amount' => $amount, 'balance_after' => $after, 'created_at' => $at,
        ]);
        $mk(40, 40, '2026-01-05 10:00:00');
        $mk(60, 100, '2026-02-03 10:00:00');
        $mk(-100, 0, '2026-02-10 10:00:00', 'settlement');
        $mk(25, 25, '2026-03-01 10:00:00');
        $this->delegate->delegateProfile()->update(['custody_balance' => 25]);

        $this->getJson("/api/v1/reports/custody/{$this->delegate->id}?from=2026-02-01&to=2026-02-28", $this->as($this->admin))
            ->assertOk()
            ->assertJsonPath('opening_balance', 40)
            ->assertJsonPath('credits', 60)
            ->assertJsonPath('debits', 100)
            ->assertJsonPath('closing_balance', 0)
            ->assertJsonPath('current_balance', 25)
            ->assertJsonCount(2, 'entries');

        $this->get("/api/v1/reports/custody/{$this->delegate->id}?all=1&format=pdf", $this->as($this->admin))->assertOk();
        $this->getJson("/api/v1/reports/custody/{$this->customer->id}", $this->as($this->admin))->assertNotFound();
    }

    public function test_wallet_statement_for_admin_and_for_the_customer(): void
    {
        $wallet = Wallet::firstOrCreate(['user_id' => $this->customer->id]);
        $wallet->update(['balance' => 30]);
        WalletTransaction::forceCreate(['wallet_id' => $wallet->id, 'type' => 'topup', 'amount' => 50, 'balance_after' => 50, 'created_at' => now()->subDays(3)]);
        WalletTransaction::forceCreate(['wallet_id' => $wallet->id, 'type' => 'payment', 'amount' => -20, 'balance_after' => 30, 'created_at' => now()->subDay()]);

        $this->getJson("/api/v1/reports/wallet/{$wallet->id}", $this->as($this->admin))
            ->assertOk()->assertJsonPath('closing_balance', 30)->assertJsonPath('customer.id', $this->customer->id);

        $this->getJson('/api/v1/customer/wallet/statement', $this->as($this->customer))
            ->assertOk()->assertJsonPath('credits', 50)->assertJsonPath('debits', 20);
        $res = $this->get('/api/v1/customer/wallet/statement?format=pdf', $this->as($this->customer))->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_reports_need_the_reports_permission(): void
    {
        $type = UserType::create(['name' => 'clerk']);
        $clerk = AppUser::factory()->create(['user_type_id' => $type->id]);

        $this->getJson('/api/v1/reports/sales', $this->as($clerk))->assertForbidden();

        $type->permissions()->attach(Permission::firstOrCreate(['code' => 'REPORTS_VIEW']));
        $this->getJson('/api/v1/reports/sales', $this->as($clerk))->assertOk();
    }
}
