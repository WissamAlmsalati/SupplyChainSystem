<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Cafe;
use App\Models\CafeBranch;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserType;
use App\Events\DelegateLocationUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DelegateMobileEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $delegate;
    protected UserType $delegateType;
    protected CafeBranch $branch;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->delegateType = UserType::create(['name' => 'delegate']);
        UserType::create(['name' => 'cafe']);

        Permission::create(['code' => 'ORDERS_CREATE']);
        $this->delegateType->permissions()->sync([Permission::where('code', 'ORDERS_CREATE')->value('id')]);

        $cafe = Cafe::create(['name' => 'مقهى اختبار', 'contact_info' => '0911111111', 'is_active' => true]);

        $zone = DeliveryZone::create([
            'hex_id' => '842da29ffffffff',
            'name' => 'منطقة اختبار',
            'delivery_price' => 5,
            'latitude' => 27.0,
            'longitude' => 17.0,
            'is_active' => true,
        ]);

        $this->branch = CafeBranch::create([
            'cafe_id' => $cafe->id,
            'name' => 'فرع رئيسي',
            'city' => 'طرابلس',
            'street' => 'الشارع الرئيسي',
            'latitude' => 27.0,
            'longitude' => 17.0,
            'delivery_zone_id' => $zone->id,
            'is_active' => true,
        ]);

        $this->delegate = AppUser::create([
            'name' => 'Delegate One',
            'email' => 'delegate1@test.com',
            'mobile_number' => '0933333333',
            'password_hash' => bcrypt('password'),
            'user_type_id' => $this->delegateType->id,
            'is_active' => true,
            'is_available' => true,
            'latitude' => 27.001,
            'longitude' => 17.001,
            'location_updated_at' => now(),
        ]);

        $category = Category::create(['name' => 'تصنيف اختبار']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'منتج اختبار',
            'description' => 'وصف',
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

    protected function delegateToken(): string
    {
        $res = $this->postJson('/api/v1/login', [
            'email' => 'delegate1@test.com',
            'password' => 'password',
        ]);

        $res->assertOk();

        return $res->json('token');
    }

    public function test_delegate_can_update_location(): void
    {
        $token = $this->delegateToken();
        $res = $this->postJson('/api/v1/delegate/location', [
            'latitude' => 27.5,
            'longitude' => 17.5,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertDatabaseHas('app_user', [
            'id' => $this->delegate->id,
            'latitude' => 27.5,
            'longitude' => 17.5,
        ]);
    }

    public function test_delegate_can_set_availability(): void
    {
        $token = $this->delegateToken();
        $res = $this->postJson('/api/v1/delegate/availability', [
            'is_available' => false,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertDatabaseHas('app_user', [
            'id' => $this->delegate->id,
            'is_available' => false,
        ]);
    }

    public function test_order_auto_assigns_nearest_delegate(): void
    {
        $cafeType = UserType::where('name', 'cafe')->first();
        $cafeUser = AppUser::create([
            'name' => 'Cafe Owner',
            'email' => 'cafe@test.com',
            'mobile_number' => '0911111111',
            'password_hash' => bcrypt('password'),
            'user_type_id' => $cafeType->id,
            'cafe_id' => $this->branch->cafe_id,
            'is_active' => true,
        ]);

        $codes = ['ORDERS_CREATE', 'ORDERS_VIEW', 'CAFE_BRANCHES_VIEW'];
        $perms = collect($codes)->map(fn ($code) => Permission::firstOrCreate(['code' => $code]));
        $cafeType->permissions()->syncWithoutDetaching($perms->pluck('id'));

        $res = $this->postJson('/api/v1/login', [
            'phone_number' => '0911111111',
            'password' => 'password',
        ]);
        $res->assertOk();
        $token = $res->json('token');

        $res = $this->postJson('/api/v1/cafe/orders', [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 1,
                    'unit_price' => 10,
                ],
            ],
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated();
        $this->assertDatabaseHas('order', [
            'branch_id' => $this->branch->id,
            'delegate_id' => $this->delegate->id,
        ]);
    }

    public function test_delegate_location_update_broadcasts_event(): void
    {
        Event::fake([DelegateLocationUpdated::class]);

        $token = $this->delegateToken();
        $res = $this->postJson('/api/v1/delegate/location', [
            'latitude' => 27.5,
            'longitude' => 17.5,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        Event::assertDispatched(DelegateLocationUpdated::class);
    }

    public function test_delegate_can_list_assigned_orders(): void
    {
        $this->createAssignedOrder();

        $token = $this->delegateToken();
        $res = $this->getJson('/api/v1/delegate/orders', ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_delegate_can_filter_assigned_orders_by_status(): void
    {
        $this->createAssignedOrder(['status' => 'pending']);
        $this->createAssignedOrder(['status' => 'delivered']);

        $token = $this->delegateToken();
        $res = $this->getJson('/api/v1/delegate/orders?status=delivered', ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals('delivered', $res->json('data.0.status'));
    }

    public function test_delegate_can_view_assigned_order_detail(): void
    {
        $order = $this->createAssignedOrder();

        $token = $this->delegateToken();
        $res = $this->getJson("/api/v1/delegate/orders/{$order->id}", ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertEquals($order->id, $res->json('id'));
        $this->assertEquals($this->branch->id, $res->json('branch.id'));
    }

    public function test_delegate_cannot_view_unassigned_order_detail(): void
    {
        $order = $this->createAssignedOrder(['delegate_id' => null]);

        $token = $this->delegateToken();
        $res = $this->getJson("/api/v1/delegate/orders/{$order->id}", ['Authorization' => "Bearer $token"]);

        $res->assertNotFound();
    }

    public function test_delegate_can_update_assigned_order_status(): void
    {
        $order = $this->createAssignedOrder();

        $token = $this->delegateToken();
        $res = $this->postJson("/api/v1/delegate/orders/{$order->id}/status", [
            'status' => 'delivered',
        ], ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertDatabaseHas('order', [
            'id' => $order->id,
            'status' => 'delivered',
        ]);
    }

    public function test_delegate_cannot_update_unassigned_order_status(): void
    {
        $order = $this->createAssignedOrder(['delegate_id' => null]);

        $token = $this->delegateToken();
        $res = $this->postJson("/api/v1/delegate/orders/{$order->id}/status", [
            'status' => 'delivered',
        ], ['Authorization' => "Bearer $token"]);

        $res->assertNotFound();
    }

    public function test_delegate_cannot_set_status_other_than_delivered(): void
    {
        $order = $this->createAssignedOrder(['status' => 'pending']);

        $token = $this->delegateToken();
        $res = $this->postJson("/api/v1/delegate/orders/{$order->id}/status", [
            'status' => 'cancelled',
        ], ['Authorization' => "Bearer $token"]);

        $res->assertUnprocessable();
        $this->assertDatabaseHas('order', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    protected function createAssignedOrder(array $overrides = []): Order
    {
        $cafeType = UserType::where('name', 'cafe')->first();
        $cafeUser = AppUser::firstOrCreate(
            ['email' => 'cafeorders@test.com'],
            [
                'name' => 'Cafe Owner',
                'mobile_number' => '0944444444',
                'password_hash' => bcrypt('password'),
                'user_type_id' => $cafeType->id,
                'cafe_id' => $this->branch->cafe_id,
                'is_active' => true,
            ]
        );

        return Order::create(array_merge([
            'user_id' => $cafeUser->id,
            'branch_id' => $this->branch->id,
            'delegate_id' => $this->delegate->id,
            'delivery_zone_id' => $this->branch->delivery_zone_id,
            'delivery_fee' => 5,
            'order_date' => now(),
            'status' => 'pending',
            'source' => 'cafe_app',
            'total_amount' => 15,
        ], $overrides));
    }
}
