<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The order lifecycle is a state machine (OrderStatus::transitions()) enforced
// in the Order model, so every entry point agrees on which moves are legal.
class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private AppUser $admin;

    private AppUser $delegate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = AppUser::factory()->admin()->create();
        $this->delegate = AppUser::factory()->delegate()->create();
    }

    private function as(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    private function order(string $status, array $extra = []): Order
    {
        return Order::factory()->create(['status' => $status] + $extra);
    }

    private function move(Order $order, string $to)
    {
        return $this->putJson("/api/v1/orders/{$order->id}", ['status' => $to], $this->as($this->admin));
    }

    public function test_dashboard_walks_the_lifecycle_forward(): void
    {
        $order = $this->order('pending');

        foreach (['confirmed', 'preparing', 'out_for_delivery', 'delivered', 'received'] as $status) {
            $this->move($order, $status)->assertOk()->assertJsonPath('status', $status);
        }

        $this->assertSame(6, $order->statusLogs()->count());
    }

    public function test_show_lists_the_next_statuses(): void
    {
        $order = $this->order('preparing');

        $this->getJson("/api/v1/orders/{$order->id}", $this->as($this->admin))
            ->assertOk()
            ->assertJsonPath('next_statuses', ['out_for_delivery', 'cancelled']);
    }

    public function test_delivered_cannot_go_back_to_pending(): void
    {
        $order = $this->order('delivered');

        $this->move($order, 'pending')
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'لا يمكن نقل الطلب من «تم التوصيل» إلى «قيد الانتظار»');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);
    }

    public function test_received_and_cancelled_are_final(): void
    {
        foreach (['received', 'cancelled'] as $final) {
            $order = $this->order($final);
            foreach (['pending', 'confirmed', 'delivered'] as $to) {
                $this->move($order, $to)->assertUnprocessable();
            }
        }
    }

    public function test_pending_cannot_skip_straight_to_delivered(): void
    {
        $this->move($this->order('pending'), 'delivered')->assertUnprocessable();
    }

    public function test_rejecting_a_cancellation_request_returns_the_order_to_pending(): void
    {
        $order = $this->order('cancellation_requested');

        $this->move($order, 'pending')->assertOk();
        $this->move($order, 'confirmed')->assertOk();
    }

    public function test_setting_the_same_status_again_is_a_no_op(): void
    {
        $order = $this->order('delivered');

        $this->move($order, 'delivered')->assertOk();
        $this->assertSame(1, $order->statusLogs()->count());
    }

    public function test_delegate_must_go_out_before_delivering(): void
    {
        $order = $this->order('pending', ['delegate_id' => $this->delegate->id]);
        $url = "/api/v1/delegate/orders/{$order->id}/status";

        $this->postJson($url, ['status' => 'delivered'], $this->as($this->delegate))->assertUnprocessable();
        $this->postJson($url, ['status' => 'out_for_delivery'], $this->as($this->delegate))->assertUnprocessable();

        $this->move($order, 'confirmed')->assertOk();

        $this->getJson("/api/v1/delegate/orders/{$order->id}", $this->as($this->delegate))
            ->assertOk()->assertJsonPath('next_statuses', ['out_for_delivery']);
        $this->postJson($url, ['status' => 'out_for_delivery'], $this->as($this->delegate))->assertOk();
        $this->postJson($url, ['status' => 'delivered'], $this->as($this->delegate))->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);
    }
}
