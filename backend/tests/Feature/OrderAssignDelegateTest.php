<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AppUser;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Permission;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAssignDelegateTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $adminUser;
    protected AppUser $delegate;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $adminType = UserType::create(['name' => 'admin']);
        $delegateType = UserType::create(['name' => 'delegate']);
        UserType::create(['name' => 'cafe']);

        $codes = ['ORDERS_VIEW', 'ORDERS_EDIT', 'ORDERS_CREATE'];
        $perms = collect($codes)->map(fn ($code) => Permission::create(['code' => $code]));
        $adminType->permissions()->sync($perms->pluck('id'));

        $this->adminUser = AppUser::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'mobile_number' => '0922222222',
            'password' => bcrypt('password'),
            'user_type_id' => $adminType->id,
            'is_active' => true,
        ]);

        $this->delegate = AppUser::create([
            'name' => 'Delegate One',
            'email' => 'delegate1@test.com',
            'mobile_number' => '0933333333',
            'password' => bcrypt('password'),
            'user_type_id' => $delegateType->id,
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
        $address = Address::create([
            'user_id' => $this->adminUser->id,
            'name' => 'فرع رئيسي',
            'city' => 'طرابلس',
            'street' => 'الشارع الرئيسي',
            'latitude' => 27.0,
            'longitude' => 17.0,
            'delivery_zone_id' => $zone->id,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'user_id' => $this->adminUser->id,
            'address_id' => $address->id,
            'delivery_zone_id' => $zone->id,
            'delivery_fee' => 5,
            'subtotal' => 10,
            'placed_at' => now(),
            'status' => 'pending',
            'source' => 'dashboard',
            'total_amount' => 15,
        ]);
    }

    protected function token(): string
    {
        $res = $this->postJson('/api/v1/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $res->assertOk();

        return $res->json('token');
    }

    public function test_admin_can_assign_delegate_to_order(): void
    {
        $token = $this->token();
        $res = $this->postJson('/api/v1/orders/' . $this->order->id . '/assign-delegate', [
            'delegate_id' => $this->delegate->id,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'delegate_id' => $this->delegate->id,
        ]);
    }

    public function test_cannot_assign_inactive_delegate(): void
    {
        $this->delegate->update(['is_active' => false]);

        $token = $this->token();
        $res = $this->postJson('/api/v1/orders/' . $this->order->id . '/assign-delegate', [
            'delegate_id' => $this->delegate->id,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertUnprocessable();
    }
}
