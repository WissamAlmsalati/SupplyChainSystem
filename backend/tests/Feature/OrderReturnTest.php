<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\AppUser;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\CustodyService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Returns move stock and money together, and never more than was delivered or paid.
class OrderReturnTest extends TestCase
{
    use RefreshDatabase;

    private AppUser $admin;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = AppUser::factory()->admin()->create();
        $this->warehouse = Warehouse::factory()->create();
    }

    private function headers(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->admin->createToken('t')->plainTextToken];
    }

    /** A delivered order of 10 units at 8.00 plus 5.00 delivery = 85.00. */
    private function deliveredOrder(float $paid = 0): array
    {
        $variant = ProductVariant::factory()->create(['price' => 8, 'cost_price' => 5]);
        Inventory::create(['warehouse_id' => $this->warehouse->id, 'product_variant_id' => $variant->id, 'quantity' => 0]);

        $order = Order::factory()->create(['status' => 'delivered', 'subtotal' => 80, 'delivery_fee' => 5, 'total_amount' => 85]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_variant_id' => $variant->id, 'product_name' => 'أكواب ورقية',
            'variant_name' => '8oz', 'quantity' => 10, 'unit_price' => 8, 'unit_cost' => 5,
        ]);
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id, 'product_variant_id' => $variant->id, 'quantity_change' => -10,
            'type' => StockMovementType::Sale, 'reference_type' => $order->getMorphClass(), 'reference_id' => $order->id,
        ]);
        if ($paid > 0) {
            Payment::create(['order_id' => $order->id, 'amount' => $paid, 'method' => 'cash', 'status' => 'paid']);
        }

        return [$order, $item, $variant];
    }

    private function send(Order $order, array $items, string $method = 'wallet')
    {
        return $this->postJson('/api/v1/returns', [
            'order_id' => $order->id, 'reason' => 'وصلت مبللة', 'refund_method' => $method, 'items' => $items,
        ], $this->headers());
    }

    public function test_a_restocked_return_puts_goods_back_and_refunds_a_paid_order(): void
    {
        [$order, $item, $variant] = $this->deliveredOrder(paid: 85);

        $this->send($order, [['order_item_id' => $item->id, 'quantity' => 3, 'condition' => 'restock']])
            ->assertCreated()
            ->assertJsonPath('data.total_value', '24.00')
            ->assertJsonPath('data.refund_amount', '24.00')
            ->assertJsonPath('data.refund_method', 'wallet');

        $this->assertSame(3, Inventory::where('product_variant_id', $variant->id)->value('quantity'));
        $this->assertSame(24.0, app(WalletService::class)->balance($order->user->fresh()));
        $this->assertDatabaseHas('stock_movements', ['type' => 'return', 'quantity_change' => 3, 'reference_type' => (new OrderReturn)->getMorphClass()]);
        $this->assertDatabaseHas('notifications', ['user_id' => $order->user_id, 'title' => 'مرتجع على طلبك']);
    }

    public function test_damaged_goods_are_refunded_but_never_return_to_stock(): void
    {
        [$order, $item, $variant] = $this->deliveredOrder(paid: 85);

        $this->send($order, [['order_item_id' => $item->id, 'quantity' => 2, 'condition' => 'damaged']])->assertCreated();

        $this->assertSame(0, Inventory::where('product_variant_id', $variant->id)->value('quantity'));
        $this->assertSame(16.0, app(WalletService::class)->balance($order->user->fresh()));
    }

    public function test_an_unpaid_order_owes_less_and_no_money_moves(): void
    {
        [$order, $item] = $this->deliveredOrder(paid: 0);

        $this->send($order, [['order_item_id' => $item->id, 'quantity' => 5, 'condition' => 'restock']], 'none')
            ->assertCreated()
            ->assertJsonPath('data.refund_amount', '0.00')
            ->assertJsonPath('data.refund_method', 'none');

        $this->assertSame(0.0, app(WalletService::class)->balance($order->user->fresh()));
        // 85 - 40 returned = 45 still owed, and the payment guard agrees.
        $this->assertSame(4500, $order->fresh()->balanceCents()['outstanding']);
        $this->postJson('/api/v1/payments', ['order_id' => $order->id, 'amount' => 45.01, 'method' => 'cash', 'status' => 'paid'], $this->headers())->assertUnprocessable();
        $this->postJson('/api/v1/payments', ['order_id' => $order->id, 'amount' => 45, 'method' => 'cash', 'status' => 'paid'], $this->headers())->assertCreated();
    }

    public function test_a_part_paid_order_refunds_only_what_was_paid_beyond_the_new_total(): void
    {
        // Paid 60 of 85. Returning 40 leaves 45 owed, so 15 goes back.
        [$order, $item] = $this->deliveredOrder(paid: 60);

        $this->send($order, [['order_item_id' => $item->id, 'quantity' => 5, 'condition' => 'restock']])
            ->assertCreated()
            ->assertJsonPath('data.refund_amount', '15.00');

        $this->assertSame(0, $order->fresh()->balanceCents()['outstanding']);
    }

    public function test_more_than_was_delivered_cannot_come_back_even_across_returns(): void
    {
        [$order, $item] = $this->deliveredOrder(paid: 85);

        $this->send($order, [['order_item_id' => $item->id, 'quantity' => 7, 'condition' => 'restock']])->assertCreated();
        $this->send($order, [['order_item_id' => $item->id, 'quantity' => 4, 'condition' => 'restock']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.items.0', 'الكمية المرتجعة من «أكواب ورقية» أكبر من المتبقي (3)');
        $this->send($order, [
            ['order_item_id' => $item->id, 'quantity' => 2, 'condition' => 'restock'],
            ['order_item_id' => $item->id, 'quantity' => 2, 'condition' => 'damaged'],
        ])->assertUnprocessable();
        $this->send($order, [['order_item_id' => $item->id, 'quantity' => 3, 'condition' => 'damaged']])->assertCreated();
    }

    public function test_only_delivered_orders_take_returns_and_foreign_items_are_refused(): void
    {
        [$order, $item] = $this->deliveredOrder();
        [, $otherItem] = $this->deliveredOrder();

        $this->send($order, [['order_item_id' => $otherItem->id, 'quantity' => 1, 'condition' => 'restock']], 'none')->assertUnprocessable();

        $pending = Order::factory()->create(['status' => 'confirmed', 'subtotal' => 80, 'delivery_fee' => 5, 'total_amount' => 85]);
        $this->send($pending, [['order_item_id' => $item->id, 'quantity' => 1, 'condition' => 'restock']], 'none')
            ->assertUnprocessable()
            ->assertJsonPath('errors.order_id.0', 'المرتجع يُسجَّل بعد تسليم الطلب فقط');
    }

    public function test_a_paid_order_needs_a_refund_method(): void
    {
        [$order, $item] = $this->deliveredOrder(paid: 85);

        $this->send($order, [['order_item_id' => $item->id, 'quantity' => 1, 'condition' => 'restock']], 'none')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('refund_method');
    }

    public function test_an_order_with_a_return_can_no_longer_be_cancelled(): void
    {
        [$order, $item] = $this->deliveredOrder(paid: 85);
        $this->send($order, [['order_item_id' => $item->id, 'quantity' => 1, 'condition' => 'restock']])->assertCreated();

        $this->patchJson("/api/v1/orders/{$order->id}", ['status' => 'cancelled'], $this->headers())->assertUnprocessable();
        $this->getJson("/api/v1/orders/{$order->id}", $this->headers())
            ->assertOk()
            ->assertJsonMissing(['next_statuses' => ['cancelled']])
            ->assertJsonPath('balance.returned', 8)
            ->assertJsonPath('items.0.returned_quantity', 1);
    }

    public function test_a_cash_refund_is_pending_until_someone_hands_it_over(): void
    {
        [$order, $item] = $this->deliveredOrder(paid: 85);
        $wallet = $this->send($order, [['order_item_id' => $item->id, 'quantity' => 1, 'condition' => 'restock']])->assertCreated();
        $wallet->assertJsonPath('data.refund_pending', false);

        $cash = $this->send($order, [['order_item_id' => $item->id, 'quantity' => 2, 'condition' => 'restock']], 'cash')->assertCreated();
        $cash->assertJsonPath('data.refund_pending', true);
        $id = $cash->json('data.id');

        $this->getJson('/api/v1/returns?refund_pending=1', $this->headers())->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.summary.refund_pending', 16);

        // A delegate pays it out of the cash they hold; they cannot pay what they do not have.
        $delegate = AppUser::factory()->delegate()->create();
        $this->postJson("/api/v1/returns/{$id}/pay-refund", ['delegate_id' => $delegate->id], $this->headers())->assertUnprocessable();
        app(CustodyService::class)->adjust($delegate, 50, 'رصيد افتتاحي');

        $this->postJson("/api/v1/returns/{$id}/pay-refund", ['delegate_id' => $delegate->id], $this->headers())
            ->assertOk()->assertJsonPath('data.refund_pending', false)->assertJsonPath('data.refund_paid_from_delegate.id', $delegate->id);
        $this->assertSame('34.00', $delegate->delegateProfile()->first()->custody_balance);
        $this->assertDatabaseHas('custody_entries', ['delegate_id' => $delegate->id, 'type' => 'refund_payout', 'amount' => -16]);

        // Once, and only for cash.
        $this->postJson("/api/v1/returns/{$id}/pay-refund", [], $this->headers())->assertUnprocessable();
        $this->postJson("/api/v1/returns/{$wallet->json('data.id')}/pay-refund", [], $this->headers())->assertUnprocessable();
    }

    public function test_the_list_is_searchable_and_totals_what_it_shows(): void
    {
        [$order, $item] = $this->deliveredOrder(paid: 85);
        $this->send($order, [['order_item_id' => $item->id, 'quantity' => 2, 'condition' => 'restock']])->assertCreated();

        $this->getJson('/api/v1/returns?search='.$order->order_number, $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.summary.total_value', 16)
            ->assertJsonPath('data.0.items_quantity', 2);
        $this->getJson('/api/v1/returns?search=nothing-like-this', $this->headers())->assertOk()->assertJsonCount(0, 'data');
    }
}
