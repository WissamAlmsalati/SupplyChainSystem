<?php

namespace Tests\Feature;

use App\Enums\CartType;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\Cart;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Behaviour that the redesigned schema guarantees: profiles per user type,
// shopping vs recurring carts, order snapshots, stock ledger and purchase orders.
class DatabaseDesignTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $customer;
    protected AppUser $admin;
    protected Address $address;
    protected ProductVariant $variant;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = AppUser::factory()->customer()->create(['mobile_number' => '0911111111']);
        $this->admin = AppUser::factory()->admin()->create();

        $zone = DeliveryZone::create(['hex_id' => 'zone-1', 'delivery_price' => 5, 'is_active' => true]);
        $this->address = Address::create([
            'user_id' => $this->customer->id,
            'name' => 'فرع رئيسي',
            'city' => 'طرابلس',
            'latitude' => 32.8872,
            'longitude' => 13.1913,
            'delivery_zone_id' => $zone->id,
        ]);

        $product = Product::create(['category_id' => Category::create(['name' => 'قهوة'])->id, 'name' => 'بن عربي']);
        $this->variant = ProductVariant::create(['product_id' => $product->id, 'name' => '500 جم', 'price' => 20]);
        $this->warehouse = Warehouse::create(['name' => 'مستودع طرابلس']);
    }

    // The app instance is reused across requests in a test, so the resolved
    // sanctum user must be forgotten when switching between users.
    private function tokenHeader(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer ' . $user->createToken('t')->plainTextToken];
    }

    private function asCustomer(): array
    {
        return $this->tokenHeader($this->customer);
    }

    private function asAdmin(): array
    {
        return $this->tokenHeader($this->admin);
    }

    private function stockOnHand(): int
    {
        return (int) Inventory::where('product_variant_id', $this->variant->id)->sum('quantity');
    }

    // Goods received from the inventory screen.
    private function receiveStock(int $quantity): void
    {
        $this->postJson('/api/v1/inventory', [
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => $quantity,
            'unit_cost' => 12,
            'manufacturing_year' => 2026,
            'expiry_date' => '2027-01-01',
        ], $this->asAdmin())->assertCreated();
    }

    public function test_identical_goods_in_within_seconds_is_rejected_as_a_duplicate(): void
    {
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 150,
            'unit_cost' => 10,
        ];

        $this->postJson('/api/v1/inventory', $payload, $this->asAdmin())->assertCreated();
        $this->postJson('/api/v1/inventory', $payload, $this->asAdmin())->assertUnprocessable();

        $this->assertSame(150, $this->stockOnHand());
        $this->assertSame(1, \App\Models\StockMovement::where('product_variant_id', $this->variant->id)->count());

        // A different quantity is a real second delivery, not a duplicate.
        $this->postJson('/api/v1/inventory', ['quantity' => 40] + $payload, $this->asAdmin())->assertCreated();
        $this->assertSame(190, $this->stockOnHand());

        // And the same one is fine again once the window has passed.
        \App\Models\StockMovement::query()->update(['created_at' => now()->subMinute()]);
        $this->postJson('/api/v1/inventory', $payload, $this->asAdmin())->assertCreated();
        $this->assertSame(340, $this->stockOnHand());
    }

    public function test_each_user_type_gets_its_own_profile(): void
    {
        $delegate = AppUser::factory()->delegate()->create();

        $this->assertDatabaseHas('customer_profiles', ['user_id' => $this->customer->id]);
        $this->assertDatabaseHas('admin_profiles', ['user_id' => $this->admin->id]);
        $this->assertDatabaseHas('delegate_profiles', ['user_id' => $delegate->id, 'is_available' => false]);
        $this->assertDatabaseMissing('delegate_profiles', ['user_id' => $this->customer->id]);
    }

    public function test_only_one_shopping_cart_per_customer_but_many_recurring(): void
    {
        Cart::create(['user_id' => $this->customer->id, 'type' => CartType::Recurring, 'name' => 'أسبوعية']);
        Cart::create(['user_id' => $this->customer->id, 'type' => CartType::Recurring, 'name' => 'شهرية']);
        Cart::create(['user_id' => $this->customer->id, 'type' => CartType::Shopping]);

        $this->expectException(QueryException::class);
        Cart::create(['user_id' => $this->customer->id, 'type' => CartType::Shopping]);
    }

    public function test_receiving_goods_from_inventory_records_cost_and_expiry(): void
    {
        $this->receiveStock(30);
        $this->receiveStock(20);

        $this->assertSame(50, $this->stockOnHand());
        $this->assertDatabaseCount('inventories', 1);
        $this->assertDatabaseHas('stock_movements', [
            'type' => 'purchase', 'quantity_change' => 30, 'unit_cost' => 12, 'manufacturing_year' => 2026,
            'expiry_date' => '2027-01-01', 'created_by' => $this->admin->id,
        ]);
        $this->getJson('/api/v1/stock-movements?type=purchase', $this->asAdmin())
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.expiry_date', '2027-01-01');

        // Counting stock is an adjustment and cannot carry goods-in details.
        $inventory = \App\Models\Inventory::first();
        $this->putJson("/api/v1/inventory/{$inventory->id}", ['quantity' => 45, 'expiry_date' => '2028-01-01'], $this->asAdmin())->assertUnprocessable();
    }

    public function test_recurring_cart_can_be_ordered_repeatedly_and_is_kept(): void
    {
        $this->receiveStock(50);

        $cart = $this->postJson('/api/v1/customer/recurring-carts', [
            'name' => 'الطلبية الأسبوعية',
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 3]],
        ], $this->asCustomer())->assertCreated()
            ->assertJsonPath('data.type', 'recurring')
            ->assertJsonPath('data.subtotal', 60)
            ->json('data');

        foreach ([1, 2] as $_) {
            $this->postJson("/api/v1/customer/recurring-carts/{$cart['id']}/order", [
                'address_id' => $this->address->id,
            ], $this->asCustomer())->assertCreated();
        }

        $this->assertSame(2, Order::where('cart_id', $cart['id'])->count());
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart['id'], 'quantity' => 3]);
        $this->assertSame(44, $this->stockOnHand());
        $this->getJson('/api/v1/customer/recurring-carts', $this->asCustomer())->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_recurring_order_uses_current_price(): void
    {
        $this->receiveStock(10);
        $cart = Cart::create(['user_id' => $this->customer->id, 'type' => CartType::Recurring, 'name' => 'أسبوعية']);
        $cart->items()->create(['product_variant_id' => $this->variant->id, 'quantity' => 1]);

        $this->variant->update(['price' => 25]);

        $this->postJson("/api/v1/customer/recurring-carts/{$cart->id}/order", [
            'address_id' => $this->address->id,
        ], $this->asCustomer())->assertCreated()->assertJsonPath('data.total_amount', '30.00');
    }

    public function test_order_keeps_address_and_product_snapshot_after_edits(): void
    {
        $this->receiveStock(10);

        $orderId = $this->postJson('/api/v1/customer/orders', [
            'address_id' => $this->address->id,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
        ], $this->asCustomer())->assertCreated()->json('data.id');

        $this->address->update(['name' => 'اسم جديد', 'city' => 'بنغازي']);
        $this->variant->update(['name' => '1 كجم', 'price' => 99]);
        $this->variant->product->update(['name' => 'منتج معدل']);

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'delivery_address_name' => 'فرع رئيسي', 'delivery_city' => 'طرابلس', 'subtotal' => 40]);
        $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'product_name' => 'بن عربي', 'variant_name' => '500 جم', 'unit_price' => 20]);
    }

    public function test_order_without_enough_stock_is_rejected_and_changes_nothing(): void
    {
        $this->receiveStock(1);

        $this->postJson('/api/v1/customer/orders', [
            'address_id' => $this->address->id,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 5]],
        ], $this->asCustomer())->assertStatus(409)->assertJsonPath('shortages.0.available', 1);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $this->stockOnHand());
    }

    public function test_status_changes_are_logged_and_cancellation_restocks(): void
    {
        $this->receiveStock(10);

        $orderId = $this->postJson('/api/v1/customer/orders', [
            'address_id' => $this->address->id,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 4]],
        ], $this->asCustomer())->assertCreated()->json('data.id');
        $this->assertSame(6, $this->stockOnHand());

        $this->putJson("/api/v1/orders/{$orderId}", ['status' => 'confirmed'], $this->asAdmin())->assertOk();
        $this->putJson("/api/v1/orders/{$orderId}", ['status' => 'cancelled'], $this->asAdmin())->assertOk();
        $this->putJson("/api/v1/orders/{$orderId}", ['status' => 'bogus'], $this->asAdmin())->assertUnprocessable();

        $this->assertSame(10, $this->stockOnHand());
        $this->assertSame(
            [[null, 'pending'], ['pending', 'confirmed'], ['confirmed', 'cancelled']],
            Order::find($orderId)->statusLogs->map(fn ($l) => [$l->from_status, $l->to_status])->all()
        );
        $this->assertDatabaseHas('stock_movements', ['reference_id' => $orderId, 'type' => 'sale', 'quantity_change' => -4]);
        $this->assertDatabaseHas('stock_movements', ['reference_id' => $orderId, 'type' => 'return', 'quantity_change' => 4]);
    }

    public function test_manual_stock_count_is_recorded_as_adjustment(): void
    {
        $this->receiveStock(10);
        $inventory = Inventory::first();

        $this->putJson("/api/v1/inventory/{$inventory->id}", ['quantity' => 7, 'note' => 'جرد'], $this->asAdmin())
            ->assertOk()->assertJsonPath('quantity', 7);

        $this->assertDatabaseHas('stock_movements', ['type' => 'adjustment', 'quantity_change' => -3, 'note' => 'جرد']);
        $this->getJson('/api/v1/stock-movements?product_variant_id=' . $this->variant->id, $this->asAdmin())
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_product_and_variant_images_share_one_table(): void
    {
        $product = $this->variant->product;

        $this->postJson('/api/v1/product-images', ['product_id' => $product->id, 'url' => 'https://cdn.test/p.jpg', 'is_primary' => true], $this->asAdmin())
            ->assertCreated()->assertJsonPath('image_url', 'https://cdn.test/p.jpg');
        $this->postJson('/api/v1/product-images', ['product_variant_id' => $this->variant->id, 'url' => 'https://cdn.test/v.jpg'], $this->asAdmin())
            ->assertCreated()->assertJsonPath('product_id', $product->id);

        $this->assertSame('https://cdn.test/p.jpg', $product->fresh()->image_url);
        $this->assertCount(1, $product->images);
        $this->assertCount(1, $this->variant->images);
    }

    public function test_soft_deleted_catalog_rows_stay_linked_to_orders(): void
    {
        $this->receiveStock(5);
        $orderId = $this->postJson('/api/v1/customer/orders', [
            'address_id' => $this->address->id,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
        ], $this->asCustomer())->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/product-variants/{$this->variant->id}", [], $this->asAdmin())->assertNoContent();
        $this->deleteJson("/api/v1/products/{$this->variant->product_id}", [], $this->asAdmin())->assertNoContent();

        $this->assertSoftDeleted('product_variants', ['id' => $this->variant->id]);
        $this->getJson("/api/v1/orders/{$orderId}", $this->asAdmin())
            ->assertOk()
            ->assertJsonPath('items.0.product_variant.id', $this->variant->id)
            ->assertJsonPath('items.0.product_variant.product.name', 'بن عربي');
    }
}
