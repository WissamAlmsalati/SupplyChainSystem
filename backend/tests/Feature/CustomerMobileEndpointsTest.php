<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AppUser;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Permission;
use App\Models\PremiumFeature;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserType;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerMobileEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $customerUser;

    protected Address $address;

    protected ProductVariant $variant;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $customerType = UserType::firstOrCreate(['name' => 'customer']);
        UserType::firstOrCreate(['name' => 'admin']);
        UserType::firstOrCreate(['name' => 'super_admin']);
        UserType::firstOrCreate(['name' => 'delegate']);

        $permissions = collect([
            'ORDERS_VIEW', 'ORDERS_EDIT', 'ORDERS_CREATE',
            'CUSTOMER_BRANCHES_VIEW', 'CUSTOMER_BRANCHES_CREATE', 'CUSTOMER_BRANCHES_EDIT', 'CUSTOMER_BRANCHES_DELETE',
            'INVENTORY_VIEW',
        ])->map(fn ($code) => Permission::firstOrCreate(['code' => $code]));
        $customerType->permissions()->sync($permissions->pluck('id'));

        PremiumFeature::create(['code' => 'customer_branches', 'name' => 'فروع المقاهي', 'is_active' => true]);

        $this->customerUser = AppUser::create([
            'name' => 'Customer Owner',
            'email' => 'customer@test.com',
            'mobile_number' => '0911111111',
            'password' => bcrypt('password'),
            'user_type_id' => $customerType->id,
            'is_active' => true,
        ]);

        $zone = DeliveryZone::create([
            'hex_id' => '842da29ffffffff',
            'name' => 'منطقة اختبار',
            'delivery_price' => 5,
            'latitude' => 27.0,
            'longitude' => 17.0,
            'is_active' => true,
        ]);

        $this->address = Address::create([
            'user_id' => $this->customerUser->id,
            'name' => 'فرع رئيسي',
            'city' => 'طرابلس',
            'street' => 'الشارع الرئيسي',
            'latitude' => 27.0,
            'longitude' => 17.0,
            'delivery_zone_id' => $zone->id,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'مستودع اختبار',
            'city' => 'طرابلس',
            'latitude' => 27.0,
            'longitude' => 17.0,
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

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
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
        $this->assertArrayHasKey('token', $res->json());

        return $res->json('token');
    }

    public function test_customer_login_returns_token_without_permissions(): void
    {
        $res = $this->postJson('/api/v1/login', [
            'phone_number' => '0911111111',
            'password' => 'password',
        ]);

        $res->assertOk();
        $this->assertArrayHasKey('token', $res->json());
        $this->assertArrayNotHasKey('permissions', $res->json());
    }

    public function test_customer_cannot_login_with_email(): void
    {
        $res = $this->postJson('/api/v1/login', [
            'email' => 'customer@test.com',
            'password' => 'password',
        ]);

        $res->assertForbidden()
            ->assertJsonPath('message', 'يجب تسجيل الدخول برقم الهاتف');
    }

    public function test_customer_me_returns_user(): void
    {
        $token = $this->token();
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer $token"])
            ->assertOk()
            ->assertJsonPath('email', 'customer@test.com')
            ->assertJsonPath('has_addresses', true);
    }

    public function test_customer_profile(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/v1/customer/profile', ['Authorization' => "Bearer $token"]);
        $res->assertOk()
            ->assertJsonPath('user.email', 'customer@test.com')
            ->assertJsonPath('user.has_addresses', true)
            ->assertJsonPath('user.addresses_count', 1)
            ->assertJsonMissingPath('has_customer');
        $this->assertCount(1, $res->json('addresses'));
    }

    public function test_customer_profile_without_addresses(): void
    {
        $token = $this->token();
        Address::query()->delete();

        $this->getJson('/api/v1/customer/profile', ['Authorization' => "Bearer $token"])
            ->assertOk()
            ->assertJsonPath('user.has_addresses', false)
            ->assertJsonPath('user.addresses_count', 0);

        $this->getJson('/api/v1/me', ['Authorization' => "Bearer $token"])
            ->assertOk()
            ->assertJsonPath('has_addresses', false);
    }

    public function test_customer_addresses_list(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/v1/customer/addresses', ['Authorization' => "Bearer $token"]);
        $res->assertOk();
        $this->assertCount(1, $res->json('data.addresses'));
        // Branches can sit in different zones, so the list carries no single fee;
        // each address carries its own through its zone.
        $this->assertArrayNotHasKey('delivery_price', $res->json('data'));
        $this->assertEquals(5, $res->json('data.addresses.0.delivery_zone.delivery_price'));
    }

    public function test_customer_address_create(): void
    {
        $token = $this->token();
        // The point has to fall inside a delivery zone: this is its res-4 cell.
        DeliveryZone::create(['hex_id' => '84384b3ffffffff', 'name' => 'طرابلس', 'delivery_price' => 7, 'is_active' => true]);
        $res = $this->postJson('/api/v1/customer/addresses', [
            'name' => 'عنوان جديد',
            'city' => 'طرابلس',
            'street' => 'شارع جمال',
            'latitude' => 32.88,
            'longitude' => 13.19,
            'contact_phones' => ['0912345678'],
            'is_active' => true,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated();
        $this->assertDatabaseHas('addresses', ['name' => 'عنوان جديد']);
    }

    private function placeOrder(string $token, int $quantity = 3): int
    {
        return $this->postJson('/api/v1/customer/orders', [
            'address_id' => $this->address->id,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => $quantity, 'unit_price' => 10]],
        ], ['Authorization' => "Bearer $token"])->assertCreated()->json('data.id')
            ?? Order::latest('id')->value('id');
    }

    public function test_customer_addresses_list_includes_details_without_orders(): void
    {
        $token = $this->token();
        $this->placeOrder($token, 3);

        $address = $this->getJson('/api/v1/customer/addresses', ['Authorization' => "Bearer $token"])
            ->assertOk()->json('data.addresses.0');

        $this->assertSame('الشارع الرئيسي، طرابلس', $address['full_address']);
        $this->assertSame('منطقة اختبار', $address['delivery_zone']['name']);
        $this->assertEquals(5, $address['delivery_zone']['delivery_price']);
        $this->assertArrayNotHasKey('delivery_price', $address);
        $this->assertArrayNotHasKey('is_default', $address);
        $this->assertArrayNotHasKey('is_active', $address);
        $this->assertArrayNotHasKey('stats', $address);
        $this->assertArrayNotHasKey('last_order', $address);

        $this->getJson('/api/v1/customer/addresses/'.$this->address->id, ['Authorization' => "Bearer $token"])
            ->assertOk()
            ->assertJsonPath('id', $this->address->id)
            ->assertJsonPath('full_address', 'الشارع الرئيسي، طرابلس')
            ->assertJsonMissingPath('stats');
    }

    public function test_customer_address_orders(): void
    {
        $token = $this->token();
        $pending = $this->placeOrder($token, 3);
        $cancelled = $this->placeOrder($token, 1);
        Order::find($cancelled)->update(['status' => 'cancelled']);

        $url = '/api/v1/customer/addresses/'.$this->address->id.'/orders';
        $res = $this->getJson($url, ['Authorization' => "Bearer $token"])->assertOk();

        $res->assertJsonMissingPath('address')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.counts.all', 2)
            ->assertJsonPath('meta.counts.active', 1)
            ->assertJsonPath('meta.counts.cancelled', 1)
            ->assertJsonPath('meta.counts.by_status.pending', 1);
        $card = collect($res->json('data'))->firstWhere('id', $pending);
        $this->assertSame('pending', $card['status']);
        $this->assertSame('قيد الانتظار', $card['status_label']);
        $this->assertSame(1, $card['items_count']);
        $this->assertTrue($card['can_cancel']);
        $this->assertFalse($card['can_confirm_receipt']);
        $this->assertArrayNotHasKey('user', $card);

        // Tab filter narrows the list but keeps the tab counts.
        $this->getJson($url.'?group=cancelled', ['Authorization' => "Bearer $token"])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cancelled)
            ->assertJsonPath('meta.counts.all', 2);

        $this->getJson($url.'?status=pending,bogus', ['Authorization' => "Bearer $token"])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.applied.status', ['pending']);

        $this->getJson($url.'?group=wrong', ['Authorization' => "Bearer $token"])->assertStatus(422);
    }

    public function test_customer_cannot_read_another_users_address_orders(): void
    {
        $token = $this->token();
        $other = Address::create([
            'user_id' => AppUser::create([
                'name' => 'Other Customer', 'email' => 'other@test.com', 'mobile_number' => '0912222222',
                'password' => bcrypt('password'), 'user_type_id' => $this->customerUser->user_type_id,
            ])->id,
            'name' => 'فرع غريب', 'city' => 'مصراتة', 'street' => 'ش', 'latitude' => 27, 'longitude' => 17,
        ]);

        $this->getJson('/api/v1/customer/addresses/'.$other->id.'/orders', ['Authorization' => "Bearer $token"])->assertNotFound();
        $this->getJson('/api/v1/customer/addresses/'.$other->id, ['Authorization' => "Bearer $token"])->assertNotFound();
    }

    public function test_customer_orders_list(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/v1/customer/orders', ['Authorization' => "Bearer $token"]);
        $res->assertOk();
        $this->assertIsArray($res->json('data'));
    }

    public function test_customer_order_create(): void
    {
        $token = $this->token();
        $res = $this->postJson('/api/v1/customer/orders', [
            'address_id' => $this->address->id,
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 3,
                    'unit_price' => 10,
                ],
            ],
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated();
        $this->assertDatabaseHas('orders', [
            'address_id' => $this->address->id,
            'user_id' => $this->customerUser->id,
            'status' => 'pending',
        ]);
    }

    public function test_customer_categories_list(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/v1/customer/categories', ['Authorization' => "Bearer $token"]);
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_customer_products_list(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/v1/customer/products', ['Authorization' => "Bearer $token"]);
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_customer_product_variants(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/v1/customer/products/'.$this->variant->product_id.'/variants', ['Authorization' => "Bearer $token"]);
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    // The dashboard's stock list shows every warehouse; a cafe reads stock
    // through /customer/products and /customer/cart/check-stock instead.
    public function test_the_dashboard_inventory_list_is_not_the_customers_to_read(): void
    {
        $token = $this->token();
        $this->getJson('/api/v1/inventory', ['Authorization' => "Bearer $token"])->assertForbidden();
    }

    public function test_customer_can_list_delivery_zones_for_map(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/v1/customer/delivery-zones', ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertArrayHasKey('hex_id', $res->json('data.0'));
        $this->assertArrayHasKey('delivery_price', $res->json('data.0'));
    }
}
