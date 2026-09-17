<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Wallet;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustodyTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $customer;
    protected AppUser $admin;
    protected AppUser $delegate;
    protected Address $address;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = AppUser::factory()->customer()->create(['mobile_number' => '0911111111']);
        $this->admin = AppUser::factory()->admin()->create();
        $this->delegate = AppUser::factory()->delegate()->create();

        $zone = DeliveryZone::create(['hex_id' => 'z1', 'delivery_price' => 5, 'is_active' => true]);
        $this->address = Address::create(['user_id' => $this->customer->id, 'name' => 'فرع', 'latitude' => 32.8, 'longitude' => 13.1, 'delivery_zone_id' => $zone->id]);
        $product = Product::create(['category_id' => Category::create(['name' => 'قهوة'])->id, 'name' => 'بن']);
        $this->variant = ProductVariant::create(['product_id' => $product->id, 'name' => '1 كجم', 'price' => 45]);
        app(StockService::class)->adjust(Warehouse::create(['name' => 'م'])->id, $this->variant->id, 50, StockMovementType::Adjustment);
    }

    private function as(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer ' . $user->createToken('t')->plainTextToken];
    }

    private function custody(): float
    {
        return (float) $this->delegate->delegateProfile()->value('custody_balance');
    }

    private function order(string $method = 'cash'): int
    {
        $id = $this->postJson('/api/v1/customer/orders', [
            'address_id' => $this->address->id,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
            'payment_method' => $method,
        ], $this->as($this->customer))->assertCreated()->json('data.id');

        // Assigned and already on the road, so the delegate may mark it delivered.
        Order::whereKey($id)->update(['delegate_id' => $this->delegate->id, 'status' => 'out_for_delivery']);

        return $id;
    }

    private function deliver(int $orderId)
    {
        return $this->postJson("/api/v1/delegate/orders/{$orderId}/status", ['status' => 'delivered'], $this->as($this->delegate));
    }

    public function test_delivering_a_cash_order_puts_the_cash_in_the_delegates_custody_once(): void
    {
        $orderId = $this->order();

        $this->deliver($orderId)->assertOk()->assertJsonPath('cash_collected', 50)->assertJsonPath('custody_balance', '50.00');
        $this->deliver($orderId)->assertOk()->assertJsonPath('cash_collected', 0);

        $this->assertSame(50.0, $this->custody());
        $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'method' => 'cash', 'status' => 'paid', 'amount' => 50, 'collected_by' => $this->delegate->id]);
        $this->assertDatabaseCount('custody_entries', 1);
        $this->assertDatabaseHas('custody_entries', ['type' => 'order_collection', 'amount' => 50, 'reference_id' => $orderId]);
    }

    public function test_wallet_paid_orders_add_nothing_to_custody(): void
    {
        $wallet = Wallet::where('user_id', $this->customer->id)->first();
        $this->postJson("/api/v1/wallets/{$wallet->id}/adjust", ['amount' => 100, 'note' => 'رصيد'], $this->as($this->admin))->assertOk();

        $this->deliver($this->order('wallet'))->assertOk()->assertJsonPath('cash_collected', 0);

        $this->assertSame(0.0, $this->custody());
        $this->assertDatabaseCount('custody_entries', 0);
    }

    public function test_admin_marking_delivered_also_records_the_assigned_delegates_collection(): void
    {
        $orderId = $this->order();
        $this->putJson("/api/v1/orders/{$orderId}", ['status' => 'delivered'], $this->as($this->admin))->assertOk();

        $this->assertSame(50.0, $this->custody());
    }

    public function test_cash_collected_for_a_wallet_top_up_is_custody_too(): void
    {
        $this->postJson('/api/v1/delegate/wallet/collect', ['mobile_number' => '0911111111', 'amount' => 120], $this->as($this->delegate))->assertCreated();

        $this->assertSame(120.0, $this->custody());
        $this->assertDatabaseHas('custody_entries', ['type' => 'wallet_collection', 'amount' => 120]);
    }

    public function test_settlement_reduces_custody_and_cannot_exceed_it(): void
    {
        $this->deliver($this->order())->assertOk();
        $this->postJson('/api/v1/delegate/wallet/collect', ['mobile_number' => '0911111111', 'amount' => 30], $this->as($this->delegate))->assertCreated();

        $this->postJson("/api/v1/custody/{$this->delegate->id}/settle", ['amount' => 81], $this->as($this->admin))->assertUnprocessable();
        $this->postJson("/api/v1/custody/{$this->delegate->id}/settle", ['amount' => 60, 'note' => 'تسليم مساء'], $this->as($this->admin))
            ->assertCreated()->assertJsonPath('data.custody_before', '80.00')->assertJsonPath('data.custody_after', '20.00');

        $this->assertSame(20.0, $this->custody());
        $this->assertDatabaseHas('delegate_settlements', ['delegate_id' => $this->delegate->id, 'amount' => 60, 'received_by' => $this->admin->id]);

        $this->getJson("/api/v1/custody/{$this->delegate->id}", $this->as($this->admin))
            ->assertOk()->assertJsonPath('balance', '20.00')->assertJsonPath('settlements.0.amount', '60.00');
        $this->getJson("/api/v1/custody/{$this->delegate->id}/entries", $this->as($this->admin))->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/custody', $this->as($this->admin))->assertOk()->assertJsonPath('meta.summary.total_custody', 20);

        $this->getJson('/api/v1/delegate/custody', $this->as($this->delegate))
            ->assertOk()->assertJsonPath('balance', '20.00')->assertJsonPath('last_settlement.amount', '60.00')->assertJsonPath('since_last_settlement.amount', 0);

        // A collection recorded in the same second as the settlement still counts as unsettled.
        $this->postJson('/api/v1/delegate/wallet/collect', ['mobile_number' => '0911111111', 'amount' => 15], $this->as($this->delegate))->assertCreated();
        $this->getJson('/api/v1/delegate/custody', $this->as($this->delegate))
            ->assertJsonPath('since_last_settlement.amount', 15)->assertJsonPath('since_last_settlement.collections', 1);
        $this->getJson("/api/v1/custody/{$this->delegate->id}", $this->as($this->admin))->assertJsonPath('since_last_settlement.amount', 15);
        $this->getJson('/api/v1/delegate/custody/settlements', $this->as($this->delegate))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cancelling_after_cash_collection_refunds_the_customer_wallet_and_keeps_custody(): void
    {
        $orderId = $this->order();
        $this->deliver($orderId)->assertOk();

        $this->putJson("/api/v1/orders/{$orderId}", ['status' => 'cancelled'], $this->as($this->admin))->assertOk();

        $this->assertSame(50.0, $this->custody());
        $this->assertSame(50.0, (float) Wallet::where('user_id', $this->customer->id)->value('balance'));
        $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'method' => 'cash', 'status' => 'refunded']);
    }

    public function test_liquidity_summary_adds_wallets_and_delegate_cash(): void
    {
        $wallet = Wallet::where('user_id', $this->customer->id)->first();
        $this->postJson("/api/v1/wallets/{$wallet->id}/adjust", ['amount' => 100, 'note' => 'رصيد'], $this->as($this->admin))->assertOk();
        $this->post('/api/v1/customer/wallet/topups', [
            'amount' => 70, 'method' => 'bank_transfer', 'reference_number' => 'TRX-70',
            'receipt' => \Illuminate\Http\UploadedFile::fake()->image('r.jpg'),
        ], $this->as($this->customer) + ['Accept' => 'application/json'])->assertCreated();

        $this->deliver($this->order('wallet'))->assertOk();   // wallet pays 50
        $this->deliver($this->order('cash'))->assertOk();     // delegate collects 50
        $this->postJson('/api/v1/delegate/wallet/collect', ['mobile_number' => '0911111111', 'amount' => 20], $this->as($this->delegate))->assertCreated();
        $this->postJson("/api/v1/custody/{$this->delegate->id}/settle", ['amount' => 30], $this->as($this->admin))->assertCreated();

        $res = $this->getJson('/api/v1/wallets/summary?period=today', $this->as($this->admin))->assertOk();

        $res->assertJsonPath('liquidity.customer_wallets', 70)   // 100 - 50 + 20
            ->assertJsonPath('liquidity.delegate_custody', 40)   // 50 + 20 - 30
            ->assertJsonPath('liquidity.total', 110)
            ->assertJsonPath('pending_topups.count', 1)
            ->assertJsonPath('pending_topups.amount', 70)
            ->assertJsonPath('flows.wallet_payments', 50)
            ->assertJsonPath('flows.adjustments_in', 100)
            ->assertJsonPath('flows.topups_by_method.delegate_cash', 20)
            ->assertJsonPath('flows.order_cash_collected', 50)
            ->assertJsonPath('flows.wallet_cash_collected', 20)
            ->assertJsonPath('flows.settlements_received', 30)
            ->assertJsonPath('delegates.0.id', $this->delegate->id)
            ->assertJsonPath('top_wallets.0.balance', '70.00');
    }

    public function test_other_users_cannot_use_custody_endpoints(): void
    {
        $this->getJson('/api/v1/delegate/custody', $this->as($this->customer))->assertForbidden();
        \App\Models\Permission::firstOrCreate(['code' => 'CUSTODY_EDIT']);
        $this->postJson("/api/v1/custody/{$this->delegate->id}/settle", ['amount' => 1], $this->as($this->delegate))->assertForbidden();
    }
}
