<?php

namespace Tests\Feature;

use App\Enums\CartType;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserType;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CafeMobileEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $cafeUser;
    protected Address $address;
    protected ProductVariant $variant;
    protected DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $cafeType = UserType::create(['name' => 'cafe']);
        UserType::create(['name' => 'admin']);
        UserType::create(['name' => 'super_admin']);
        UserType::create(['name' => 'delegate']);

        $permissions = collect([
            'ORDERS_VIEW', 'ORDERS_EDIT', 'ORDERS_CREATE',
            'CAFE_BRANCHES_VIEW', 'CAFE_BRANCHES_CREATE', 'CAFE_BRANCHES_EDIT', 'CAFE_BRANCHES_DELETE',
            'INVENTORY_VIEW',
        ])->map(fn ($code) => Permission::create(['code' => $code]));
        $cafeType->permissions()->sync($permissions->pluck('id'));

        $this->cafeUser = AppUser::create([
            'name' => 'Cafe Owner',
            'email' => 'cafe@test.com',
            'mobile_number' => '0911111111',
            'password' => Hash::make('password'),
            'user_type_id' => $cafeType->id,
            'is_active' => true,
        ]);

        $this->zone = DeliveryZone::create([
            'hex_id' => '842da29ffffffff',
            'name' => 'منطقة اختبار',
            'delivery_price' => 5,
            'latitude' => 27.0,
            'longitude' => 17.0,
            'is_active' => true,
        ]);

        $this->address = Address::create([
            'user_id' => $this->cafeUser->id,
            'name' => 'فرع رئيسي',
            'city' => 'طرابلس',
            'street' => 'الشارع الرئيسي',
            'latitude' => 27.0,
            'longitude' => 17.0,
            'delivery_zone_id' => $this->zone->id,
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'تصنيف اختبار']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'منتج اختبار',
            'description' => 'وصف المنتج',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-001',
            'name' => 'افتراضي',
            'price' => 10,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create(['name' => 'مستودع اختبار', 'city' => 'طرابلس']);
        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 100,
        ]);
    }

    protected function token(): string
    {
        $res = $this->postJson('/api/v1/login', [
            'phone_number' => '0911111111',
            'password' => 'password',
        ]);
        $res->assertOk();

        return $res->json('token');
    }

    protected function shoppingCartWith(int $quantity): Cart
    {
        $cart = Cart::create(['user_id' => $this->cafeUser->id, 'type' => CartType::Shopping]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => $quantity,
        ]);

        return $cart;
    }

    public function test_cafe_can_get_empty_cart(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/v1/cafe/cart', ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertNull($res->json('data'));
    }

    public function test_cafe_can_add_item_to_cart(): void
    {
        $token = $this->token();
        $res = $this->postJson('/api/v1/cafe/cart/items', [
            'product_variant_id' => $this->variant->id,
            'quantity' => 3,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated()
            ->assertJsonPath('data.cart.type', 'shopping')
            ->assertJsonPath('data.cart.subtotal', 30);
        $this->assertDatabaseHas('cart_items', [
            'product_variant_id' => $this->variant->id,
            'quantity' => 3,
        ]);
    }

    public function test_cafe_can_update_cart_item_quantity(): void
    {
        $item = $this->shoppingCartWith(1)->items()->first();

        $token = $this->token();
        $res = $this->putJson('/api/v1/cafe/cart/items/' . $item->id, [
            'quantity' => 5,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 5]);
    }

    public function test_cafe_can_remove_cart_item(): void
    {
        $item = $this->shoppingCartWith(1)->items()->first();

        $token = $this->token();
        $res = $this->deleteJson('/api/v1/cafe/cart/items/' . $item->id, [], [
            'Authorization' => "Bearer $token",
        ]);

        $res->assertOk();
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_cafe_can_clear_cart(): void
    {
        $cart = $this->shoppingCartWith(2);

        $token = $this->token();
        $res = $this->deleteJson('/api/v1/cafe/cart', [], ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        // The shopping cart row is kept (one per user); only its items go.
        $this->assertDatabaseHas('carts', ['id' => $cart->id]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
    }

    public function test_cafe_can_checkout_cart(): void
    {
        $cart = $this->shoppingCartWith(2);

        $token = $this->token();
        $res = $this->postJson('/api/v1/cafe/cart/checkout', [
            'address_id' => $this->address->id,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated()
            ->assertJsonPath('data.total_amount', '25.00')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->cafeUser->id,
            'address_id' => $this->address->id,
            'cart_id' => $cart->id,
            'delivery_address_name' => 'فرع رئيسي',
            'subtotal' => 20.00,
            'total_amount' => 25.00,
        ]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
    }

    public function test_checkout_empty_cart_fails(): void
    {
        Cart::create(['user_id' => $this->cafeUser->id, 'type' => CartType::Shopping]);

        $token = $this->token();
        $res = $this->postJson('/api/v1/cafe/cart/checkout', [
            'address_id' => $this->address->id,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertStatus(400)
            ->assertJsonPath('message', 'السلة فارغة');
    }

    public function test_cafe_cannot_checkout_to_foreign_address(): void
    {
        $foreignAddress = Address::create([
            'user_id' => AppUser::factory()->customer()->create()->id,
            'name' => 'عنوان آخر',
            'latitude' => 27.0,
            'longitude' => 17.0,
        ]);
        $this->shoppingCartWith(1);

        $token = $this->token();
        $res = $this->postJson('/api/v1/cafe/cart/checkout', [
            'address_id' => $foreignAddress->id,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertNotFound();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cafe_can_see_delegate_location_for_own_order(): void
    {
        $delegate = AppUser::create([
            'name' => 'Delegate',
            'email' => 'delegate@test.com',
            'mobile_number' => '0999999999',
            'password' => Hash::make('password'),
            'user_type_id' => UserType::where('name', 'delegate')->value('id'),
            'is_active' => true,
        ]);
        $delegate->delegateProfile->update([
            'latitude' => 27.1,
            'longitude' => 17.1,
            'location_updated_at' => now(),
        ]);

        $order = $this->makeOrder('out_for_delivery', ['delegate_id' => $delegate->id]);

        $token = $this->token();
        $res = $this->getJson('/api/v1/cafe/orders/' . $order->id . '/delegate', [
            'Authorization' => "Bearer $token",
        ]);

        $res->assertOk()
            ->assertJsonPath('data.name', 'Delegate')
            ->assertJsonPath('data.latitude', '27.10000000')
            ->assertJsonPath('data.longitude', '17.10000000');
    }

    public function test_cafe_cannot_see_delegate_for_other_cafe_order(): void
    {
        $delegate = AppUser::factory()->delegate()->create();
        $otherUser = AppUser::factory()->customer()->create();

        $order = Order::create([
            'user_id' => $otherUser->id,
            'delegate_id' => $delegate->id,
            'status' => 'out_for_delivery',
            'subtotal' => 20,
            'delivery_fee' => 5,
            'total_amount' => 25,
        ]);

        $token = $this->token();
        $res = $this->getJson('/api/v1/cafe/orders/' . $order->id . '/delegate', [
            'Authorization' => "Bearer $token",
        ]);

        $res->assertNotFound();
    }

    public function test_cafe_dashboard_returns_enhanced_stats(): void
    {
        $order = $this->makeOrder('pending');
        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'منتج اختبار',
            'variant_name' => 'افتراضي',
            'quantity' => 2,
            'unit_price' => 10,
        ]);

        $token = $this->token();
        $res = $this->getJson('/api/v1/cafe/dashboard', ['Authorization' => "Bearer $token"]);

        $res->assertOk()
            ->assertJsonPath('stats.orders', 1);
        $this->assertEquals(25, $res->json('stats.purchases'));
        $res->assertJsonPath('stats.pending_orders', 1)
            ->assertJsonPath('periodStats.today.orders', 1)
            ->assertJsonPath('topProducts.0.total_quantity', 2)
            ->assertJsonPath('topProducts.0.variant_name', 'افتراضي')
            ->assertJsonPath('branchesComparison.0.orders_count', 1);
    }

    public function test_order_create_uses_server_side_variant_price(): void
    {
        $token = $this->token();
        $res = $this->postJson('/api/v1/cafe/orders', [
            'address_id' => $this->address->id,
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 2,
                    'unit_price' => 0.01,
                ],
            ],
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated()
            ->assertJsonPath('data.total_amount', '25.00');

        $this->assertDatabaseHas('order_items', [
            'product_variant_id' => $this->variant->id,
            'product_name' => 'منتج اختبار',
            'variant_name' => 'افتراضي',
            'unit_price' => 10.00,
        ]);
    }

    public function test_cafe_cannot_set_arbitrary_order_status(): void
    {
        $order = $this->makeOrder('pending');

        $token = $this->token();
        $res = $this->putJson('/api/v1/cafe/orders/' . $order->id . '/status', [
            'status' => 'cancelled',
        ], ['Authorization' => "Bearer $token"]);

        $res->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_cafe_can_confirm_receipt_after_delivery(): void
    {
        $order = $this->makeOrder('delivered');

        $token = $this->token();
        $res = $this->putJson('/api/v1/cafe/orders/' . $order->id . '/status', [
            'status' => 'received',
        ], ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'received']);
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id,
            'from_status' => 'delivered',
            'to_status' => 'received',
            'changed_by' => $this->cafeUser->id,
        ]);
    }

    public function test_cafe_cannot_confirm_receipt_before_delivery(): void
    {
        $order = $this->makeOrder('pending');

        $token = $this->token();
        $res = $this->putJson('/api/v1/cafe/orders/' . $order->id . '/status', [
            'status' => 'received',
        ], ['Authorization' => "Bearer $token"]);

        $res->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_cafe_can_request_cancellation_for_pending_order(): void
    {
        $order = $this->makeOrder('pending');

        $token = $this->token();
        $res = $this->postJson('/api/v1/cafe/orders/' . $order->id . '/cancel-request', [], [
            'Authorization' => "Bearer $token",
        ]);

        $res->assertOk()
            ->assertJsonPath('status', 'cancellation_requested');
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id,
            'from_status' => 'pending',
            'to_status' => 'cancellation_requested',
        ]);
    }

    public function test_cafe_cannot_request_cancellation_for_delivered_order(): void
    {
        $order = $this->makeOrder('delivered');

        $token = $this->token();
        $res = $this->postJson('/api/v1/cafe/orders/' . $order->id . '/cancel-request', [], [
            'Authorization' => "Bearer $token",
        ]);

        $res->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);
    }

    public function test_cafe_can_delete_address_without_orders(): void
    {
        $address = Address::create([
            'user_id' => $this->cafeUser->id,
            'name' => 'عنوان للحذف',
            'latitude' => 27.0,
            'longitude' => 17.0,
        ]);

        $token = $this->token();
        $res = $this->deleteJson('/api/v1/cafe/addresses/' . $address->id, [], [
            'Authorization' => "Bearer $token",
        ]);

        $res->assertOk();
        $this->assertSoftDeleted('addresses', ['id' => $address->id]);
    }

    public function test_deleting_address_with_orders_keeps_order_delivery_snapshot(): void
    {
        $order = $this->makeOrder('pending');

        $token = $this->token();
        $res = $this->deleteJson('/api/v1/cafe/addresses/' . $this->address->id, [], [
            'Authorization' => "Bearer $token",
        ]);

        $res->assertOk();
        $this->assertSoftDeleted('addresses', ['id' => $this->address->id]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'address_id' => $this->address->id,
            'delivery_address_name' => 'فرع رئيسي',
        ]);
    }

    public function test_cafe_cannot_delete_foreign_address(): void
    {
        $foreignAddress = Address::create([
            'user_id' => AppUser::factory()->customer()->create()->id,
            'name' => 'عنوان آخر',
            'latitude' => 27.0,
            'longitude' => 17.0,
        ]);

        $token = $this->token();
        $res = $this->deleteJson('/api/v1/cafe/addresses/' . $foreignAddress->id, [], [
            'Authorization' => "Bearer $token",
        ]);

        $res->assertNotFound();
    }

    private function makeOrder(string $status, array $overrides = []): Order
    {
        $order = new Order(array_merge([
            'user_id' => $this->cafeUser->id,
            'status' => $status,
            'subtotal' => 20,
            'delivery_fee' => 5,
            'total_amount' => 25,
        ], $overrides));
        $order->fillDeliveryAddress($this->address)->save();

        return $order;
    }
}
