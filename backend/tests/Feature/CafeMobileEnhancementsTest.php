<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Cafe;
use App\Models\CafeBranch;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CafeMobileEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $cafeUser;
    protected Cafe $cafe;
    protected CafeBranch $branch;
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

        $this->cafe = Cafe::create([
            'name' => 'مقهى اختبار',
            'contact_info' => '0911111111',
            'is_active' => true,
        ]);

        $this->cafeUser = AppUser::create([
            'name' => 'Cafe Owner',
            'email' => 'cafe@test.com',
            'mobile_number' => '0911111111',
            'password_hash' => Hash::make('password'),
            'user_type_id' => $cafeType->id,
            'cafe_id' => $this->cafe->id,
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

        $this->branch = CafeBranch::create([
            'cafe_id' => $this->cafe->id,
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
            'attribute_value' => 'افتراضي',
            'price' => 10,
            'is_active' => true,
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
            'branch_id' => $this->branch->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 3,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated();
        $this->assertDatabaseHas('cart_item', [
            'product_variant_id' => $this->variant->id,
            'quantity' => 3,
            'price_at_add' => 10.00,
        ]);
    }

    public function test_cafe_can_update_cart_item_quantity(): void
    {
        $token = $this->token();
        $cart = Cart::create(['user_id' => $this->cafeUser->id, 'branch_id' => $this->branch->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'price_at_add' => 10,
        ]);

        $res = $this->putJson('/api/v1/cafe/cart/items/' . $item->id, [
            'quantity' => 5,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertDatabaseHas('cart_item', ['id' => $item->id, 'quantity' => 5]);
    }

    public function test_cafe_can_remove_cart_item(): void
    {
        $token = $this->token();
        $cart = Cart::create(['user_id' => $this->cafeUser->id, 'branch_id' => $this->branch->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'price_at_add' => 10,
        ]);

        $res = $this->deleteJson('/api/v1/cafe/cart/items/' . $item->id, [], [
            'Authorization' => "Bearer $token",
        ]);

        $res->assertOk();
        $this->assertDatabaseMissing('cart_item', ['id' => $item->id]);
    }

    public function test_cafe_can_clear_cart(): void
    {
        $token = $this->token();
        $cart = Cart::create(['user_id' => $this->cafeUser->id, 'branch_id' => $this->branch->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
            'price_at_add' => 10,
        ]);

        $res = $this->deleteJson('/api/v1/cafe/cart', [], ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertDatabaseMissing('cart', ['id' => $cart->id]);
        $this->assertDatabaseMissing('cart_item', ['cart_id' => $cart->id]);
    }

    public function test_cafe_can_checkout_cart(): void
    {
        $token = $this->token();
        $cart = Cart::create(['user_id' => $this->cafeUser->id, 'branch_id' => $this->branch->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
            'price_at_add' => 10,
        ]);

        $res = $this->postJson('/api/v1/cafe/cart/checkout', [], ['Authorization' => "Bearer $token"]);

        $res->assertCreated()
            ->assertJsonPath('data.total_amount', '25.00')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('order', [
            'user_id' => $this->cafeUser->id,
            'branch_id' => $this->branch->id,
            'total_amount' => 25.00,
        ]);

        $this->assertDatabaseMissing('cart', ['id' => $cart->id]);
    }

    public function test_checkout_empty_cart_fails(): void
    {
        $token = $this->token();
        Cart::create(['user_id' => $this->cafeUser->id, 'branch_id' => $this->branch->id]);

        $res = $this->postJson('/api/v1/cafe/cart/checkout', [], ['Authorization' => "Bearer $token"]);

        $res->assertStatus(400)
            ->assertJsonPath('message', 'السلة فارغة');
    }

    public function test_cafe_cannot_add_item_to_foreign_branch(): void
    {
        $otherCafe = Cafe::create(['name' => 'مقهى آخر', 'is_active' => true]);
        $foreignBranch = CafeBranch::create([
            'cafe_id' => $otherCafe->id,
            'name' => 'فرع آخر',
            'latitude' => 27.0,
            'longitude' => 17.0,
            'is_active' => true,
        ]);

        $token = $this->token();
        $res = $this->postJson('/api/v1/cafe/cart/items', [
            'branch_id' => $foreignBranch->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertNotFound();
    }

    public function test_cafe_can_see_delegate_location_for_own_order(): void
    {
        $delegateType = UserType::where('name', 'delegate')->first();
        $delegate = AppUser::create([
            'name' => 'Delegate',
            'email' => 'delegate@test.com',
            'mobile_number' => '0999999999',
            'password_hash' => Hash::make('password'),
            'user_type_id' => $delegateType->id,
            'latitude' => 27.1,
            'longitude' => 17.1,
            'location_updated_at' => now(),
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->cafeUser->id,
            'branch_id' => $this->branch->id,
            'delegate_id' => $delegate->id,
            'delivery_zone_id' => $this->zone->id,
            'delivery_fee' => 5,
            'order_date' => now(),
            'status' => 'out_for_delivery',
            'total_amount' => 25,
        ]);

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
        $otherCafe = Cafe::create(['name' => 'مقهى آخر', 'is_active' => true]);
        $otherBranch = CafeBranch::create([
            'cafe_id' => $otherCafe->id,
            'name' => 'فرع آخر',
            'latitude' => 27.0,
            'longitude' => 17.0,
            'is_active' => true,
        ]);

        $delegateType = UserType::where('name', 'delegate')->first();
        $delegate = AppUser::create([
            'name' => 'Delegate',
            'email' => 'delegate@test.com',
            'mobile_number' => '0999999999',
            'password_hash' => Hash::make('password'),
            'user_type_id' => $delegateType->id,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->cafeUser->id,
            'branch_id' => $otherBranch->id,
            'delegate_id' => $delegate->id,
            'delivery_fee' => 5,
            'order_date' => now(),
            'status' => 'out_for_delivery',
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
        $order = Order::create([
            'user_id' => $this->cafeUser->id,
            'branch_id' => $this->branch->id,
            'delivery_zone_id' => $this->zone->id,
            'delivery_fee' => 5,
            'order_date' => now(),
            'status' => 'pending',
            'total_amount' => 25,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
            'unit_price' => 10,
        ]);

        $token = $this->token();
        $res = $this->getJson('/api/v1/cafe/dashboard', ['Authorization' => "Bearer $token"]);

        $res->assertOk()
            ->assertJsonPath('stats.orders', 1)
            ->assertJsonPath('stats.revenue', '25.00')
            ->assertJsonPath('stats.pending_orders', 1)
            ->assertJsonPath('periodStats.today.orders', 1)
            ->assertJsonPath('topProducts.0.total_quantity', 2)
            ->assertJsonPath('branchesComparison.0.orders_count', 1);
    }

    public function test_order_create_uses_server_side_variant_price(): void
    {
        $token = $this->token();
        $res = $this->postJson('/api/v1/cafe/orders', [
            'branch_id' => $this->branch->id,
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

        $this->assertDatabaseHas('order_item', [
            'product_variant_id' => $this->variant->id,
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
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_cafe_can_confirm_receipt_after_delivery(): void
    {
        $order = $this->makeOrder('delivered');
        $token = $this->token();

        $res = $this->putJson('/api/v1/cafe/orders/' . $order->id . '/status', [
            'status' => 'received',
        ], ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => 'received']);
        $this->assertDatabaseHas('order_status_log', [
            'order_id' => $order->id,
            'status' => 'received',
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
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => 'pending']);
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
        $this->assertDatabaseHas('order_status_log', [
            'order_id' => $order->id,
            'status' => 'cancellation_requested',
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
        $this->assertDatabaseHas('order', ['id' => $order->id, 'status' => 'delivered']);
    }

    public function test_cafe_can_delete_branch_without_orders(): void
    {
        $branch = CafeBranch::create([
            'cafe_id' => $this->cafe->id,
            'name' => 'فرع للحذف',
            'latitude' => 27.0,
            'longitude' => 17.0,
            'is_active' => true,
        ]);

        $token = $this->token();
        $res = $this->deleteJson('/api/v1/cafe/branches/' . $branch->id, [], [
            'Authorization' => "Bearer $token",
        ]);

        $res->assertOk();
        $this->assertDatabaseMissing('cafe_branch', ['id' => $branch->id]);
    }

    public function test_cafe_cannot_delete_branch_with_orders(): void
    {
        $order = $this->makeOrder('pending');
        $token = $this->token();

        $res = $this->deleteJson('/api/v1/cafe/branches/' . $this->branch->id, [], [
            'Authorization' => "Bearer $token",
        ]);

        $res->assertStatus(409);
        $this->assertDatabaseHas('cafe_branch', ['id' => $this->branch->id]);
    }

    public function test_cafe_cannot_delete_foreign_branch(): void
    {
        $otherCafe = Cafe::create(['name' => 'مقهى آخر', 'is_active' => true]);
        $foreignBranch = CafeBranch::create([
            'cafe_id' => $otherCafe->id,
            'name' => 'فرع آخر',
            'latitude' => 27.0,
            'longitude' => 17.0,
            'is_active' => true,
        ]);

        $token = $this->token();
        $res = $this->deleteJson('/api/v1/cafe/branches/' . $foreignBranch->id, [], [
            'Authorization' => "Bearer $token",
        ]);

        $res->assertNotFound();
    }

    private function makeOrder(string $status): Order
    {
        return Order::create([
            'user_id' => $this->cafeUser->id,
            'branch_id' => $this->branch->id,
            'delivery_zone_id' => $this->zone->id,
            'delivery_fee' => 5,
            'order_date' => now(),
            'status' => $status,
            'total_amount' => 25,
        ]);
    }
}
