<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Order notes, the failed-delivery path, status notifications and push tokens.
class OrderFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private AppUser $admin;

    private AppUser $customer;

    private AppUser $delegate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = AppUser::factory()->admin()->create();
        $this->customer = AppUser::factory()->customer()->create();
        $this->delegate = AppUser::factory()->delegate()->create(['name' => 'سالم', 'mobile_number' => '0921112222']);
    }

    private function as(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    private function order(string $status = 'confirmed'): Order
    {
        return Order::factory()->create(['user_id' => $this->customer->id, 'delegate_id' => $this->delegate->id, 'status' => $status, 'subtotal' => 95, 'delivery_fee' => 5, 'total_amount' => 100]);
    }

    private function titlesFor(AppUser $user): array
    {
        return Notification::where('user_id', $user->id)->orderBy('id')->pluck('title')->all();
    }

    public function test_an_order_carries_the_cafes_note_to_the_office_and_the_driver(): void
    {
        $zone = DeliveryZone::create(['hex_id' => 'z1', 'delivery_price' => 5, 'is_active' => true]);
        $address = Address::create(['user_id' => $this->customer->id, 'name' => 'فرع', 'latitude' => 32.8, 'longitude' => 13.1, 'delivery_zone_id' => $zone->id]);
        $product = Product::create(['category_id' => Category::create(['name' => 'قهوة'])->id, 'name' => 'بن']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => '1 كجم', 'price' => 45]);
        app(StockService::class)->adjust(Warehouse::create(['name' => 'م'])->id, $variant->id, 10, StockMovementType::Adjustment);

        $id = $this->postJson('/api/v1/customer/orders', [
            'address_id' => $address->id, 'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]], 'note' => '  اتركه عند الباب الخلفي  ',
        ], $this->as($this->customer))->assertCreated()->json('data.id');

        $this->assertSame('اتركه عند الباب الخلفي', Order::find($id)->customer_note);
        $this->getJson("/api/v1/orders/{$id}", $this->as($this->admin))->assertOk()->assertJsonPath('customer_note', 'اتركه عند الباب الخلفي');

        Order::find($id)->update(['delegate_id' => $this->delegate->id]);
        $this->getJson("/api/v1/delegate/orders/{$id}", $this->as($this->delegate))->assertOk()->assertJsonPath('customer_note', 'اتركه عند الباب الخلفي');

        $this->postJson('/api/v1/customer/orders', ['address_id' => $address->id, 'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]], 'note' => str_repeat('ن', 501)], $this->as($this->customer))->assertUnprocessable();
    }

    public function test_a_failed_delivery_has_a_reason_and_can_be_tried_again(): void
    {
        $order = $this->order('preparing');
        $status = fn (array $body) => $this->postJson("/api/v1/delegate/orders/{$order->id}/status", $body, $this->as($this->delegate));

        $status(['status' => 'out_for_delivery'])->assertOk();
        $this->getJson("/api/v1/delegate/orders/{$order->id}", $this->as($this->delegate))->assertOk()
            ->assertJsonPath('next_statuses', ['delivered', 'delivery_failed'])
            ->assertJsonPath('amount_to_collect', 100)
            ->assertJsonPath('failure_reasons.customer_absent', 'الزبون غير موجود في العنوان');

        $status(['status' => 'delivery_failed'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $status(['status' => 'delivery_failed', 'reason' => 'other'])->assertUnprocessable()->assertJsonValidationErrors('note');
        $status(['status' => 'delivery_failed', 'reason' => 'customer_absent', 'note' => 'المحل مقفل'])->assertOk();

        $order->refresh();
        $this->assertSame('delivery_failed', $order->status->value);
        $this->assertSame('الزبون غير موجود في العنوان', $order->delivery_failure_label);
        $this->assertSame(1, $order->delivery_attempts);
        $this->assertDatabaseHas('order_status_logs', ['order_id' => $order->id, 'to_status' => 'delivery_failed', 'note' => 'الزبون غير موجود في العنوان: المحل مقفل']);
        // Nothing was collected, so nothing sits in custody.
        $this->assertSame(0, $order->payments()->count());

        // Nobody can call it delivered from here without going out again.
        $status(['status' => 'delivered'])->assertUnprocessable();
        $status(['status' => 'out_for_delivery'])->assertOk();
        $status(['status' => 'delivered'])->assertOk()->assertJsonPath('cash_collected', 100);
        $this->assertSame(2, $order->fresh()->delivery_attempts);

        $this->assertContains('تعذّر توصيل طلب', $this->titlesFor($this->admin));
        $this->assertContains('delivery_failed', OrderStatus::groups()['active']);
    }

    public function test_the_cafe_hears_about_every_step_but_not_its_own(): void
    {
        $order = $this->order('pending');
        $move = fn (string $to) => $this->patchJson("/api/v1/orders/{$order->id}", ['status' => $to], $this->as($this->admin))->assertOk();

        $move('confirmed');
        $move('preparing');
        $move('out_for_delivery');
        $move('delivered');

        $this->assertSame(['تم تأكيد طلبك', 'طلبك قيد التجهيز', 'طلبك في الطريق', 'تم توصيل طلبك'], $this->titlesFor($this->customer));
        $onTheWay = Notification::where('user_id', $this->customer->id)->where('title', 'طلبك في الطريق')->first();
        $this->assertStringContainsString('سالم', $onTheWay->message);
        $this->assertSame(['order', $order->id], [$onTheWay->entity_type, (int) $onTheWay->entity_id]);

        // Confirming receipt is the cafe's own act: the office is told, the cafe is not.
        $this->patchJson("/api/v1/customer/orders/{$order->id}/status", ['status' => 'received'], $this->as($this->customer))->assertOk();
        $this->assertCount(4, $this->titlesFor($this->customer));
        $this->assertContains('أكّد الزبون الاستلام', $this->titlesFor($this->admin));
    }

    public function test_a_rejected_cancellation_a_cancelled_job_and_an_approval_are_all_told(): void
    {
        $order = $this->order('pending');
        $this->postJson("/api/v1/customer/orders/{$order->id}/cancel-request", ['reason' => 'خطأ في الكمية'], $this->as($this->customer))->assertSuccessful();
        $this->patchJson("/api/v1/orders/{$order->id}", ['status' => 'pending'], $this->as($this->admin))->assertOk();
        $this->assertContains('رُفض طلب الإلغاء', $this->titlesFor($this->customer));

        $other = $this->order('confirmed');
        $this->patchJson("/api/v1/orders/{$other->id}", ['status' => 'cancelled'], $this->as($this->admin))->assertOk();
        $this->assertContains('أُلغي طلب مسند إليك', $this->titlesFor($this->delegate));

        $pending = AppUser::factory()->customer()->create(['is_active' => false]);
        $pending->update(['is_active' => true]);
        $this->assertSame(['تم تفعيل حسابك'], $this->titlesFor($pending));
    }

    public function test_a_driver_is_told_when_a_job_is_given_to_them(): void
    {
        $order = Order::factory()->create(['user_id' => $this->customer->id, 'status' => 'confirmed', 'delivery_address_name' => 'فرع الظهرة', 'delivery_city' => 'طرابلس']);

        $this->postJson("/api/v1/orders/{$order->id}/assign-delegate", ['delegate_id' => $this->delegate->id], $this->as($this->admin))->assertOk();

        $note = Notification::where('user_id', $this->delegate->id)->first();
        $this->assertSame('طلب جديد مسند إليك', $note->title);
        $this->assertStringContainsString('فرع الظهرة', $note->message);
    }

    public function test_a_phone_registers_for_push_and_stops_on_sign_out(): void
    {
        $token = str_repeat('fcm-token-', 6);
        $headers = $this->as($this->customer);

        $this->postJson('/api/v1/customer/devices', ['token' => $token, 'platform' => 'android', 'device_name' => 'Samsung A54'], $headers)
            ->assertOk()->assertJsonPath('device.app', 'customer')->assertJsonMissingPath('device.token');
        $this->postJson('/api/v1/customer/devices', ['token' => $token, 'platform' => 'android'], $headers)->assertOk();
        $this->assertSame(1, DeviceToken::count());

        // The same phone, another account: the token follows whoever is signed in.
        $this->postJson('/api/v1/delegate/devices', ['token' => $token, 'platform' => 'android'], $this->as($this->delegate))->assertOk()->assertJsonPath('device.app', 'delegate');
        $this->assertSame($this->delegate->id, DeviceToken::first()->user_id);

        $this->postJson('/api/v1/customer/devices', ['token' => 'short', 'platform' => 'android'], $this->as($this->customer))->assertUnprocessable();
        $this->postJson('/api/v1/customer/devices', ['token' => $token, 'platform' => 'symbian'], $this->as($this->customer))->assertUnprocessable();

        // Someone else cannot unregister it; sign-out with the token does.
        $this->deleteJson('/api/v1/customer/devices', ['token' => $token], $this->as($this->customer))->assertOk();
        $this->assertSame(1, DeviceToken::count());
        $this->postJson('/api/v1/delegate/logout', ['device_token' => $token], $this->as($this->delegate))->assertOk();
        $this->assertSame(0, DeviceToken::count());

        // Switching an account off stops pushing to its phones.
        $this->postJson('/api/v1/customer/devices', ['token' => $token, 'platform' => 'ios'], $this->as($this->customer))->assertOk();
        $this->customer->update(['is_active' => false]);
        $this->assertSame(0, DeviceToken::count());
    }
}
