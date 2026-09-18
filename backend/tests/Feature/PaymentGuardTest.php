<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Payments can never exceed what is still owed on the order.
class PaymentGuardTest extends TestCase
{
    use RefreshDatabase;

    private AppUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = AppUser::factory()->admin()->create();
    }

    private function headers(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->admin->createToken('t')->plainTextToken];
    }

    private function pay(Order $order, float $amount, string $status = 'paid')
    {
        return $this->postJson('/api/v1/payments', [
            'order_id' => $order->id, 'amount' => $amount, 'method' => 'cash', 'status' => $status, 'paid_at' => now()->toDateTimeString(),
        ], $this->headers());
    }

    public function test_a_payment_cannot_exceed_the_order_total(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'subtotal' => 95, 'delivery_fee' => 5, 'total_amount' => 100]);

        $this->pay($order, 100.01)->assertUnprocessable()->assertJsonPath('errors.amount.0', 'المبلغ يتجاوز المتبقي على الطلب (100.00 د.ل)');
        $this->pay($order, 0)->assertUnprocessable();
        $this->pay($order, 100)->assertCreated();
    }

    public function test_partial_payments_stop_at_the_remaining_balance(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'subtotal' => 95, 'delivery_fee' => 5, 'total_amount' => 100]);

        $this->pay($order, 60)->assertCreated();
        $this->pay($order, 40.5)->assertUnprocessable()->assertJsonPath('errors.amount.0', 'المبلغ يتجاوز المتبقي على الطلب (40.00 د.ل)');
        $this->pay($order, 40)->assertCreated();
        $this->pay($order, 0.01)->assertUnprocessable();
    }

    public function test_editing_a_payment_respects_the_other_payments(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'subtotal' => 95, 'delivery_fee' => 5, 'total_amount' => 100]);
        $first = Payment::create(['order_id' => $order->id, 'amount' => 60, 'method' => 'cash', 'status' => 'paid']);
        Payment::create(['order_id' => $order->id, 'amount' => 30, 'method' => 'cash', 'status' => 'paid']);

        $body = fn (float $amount) => ['order_id' => $order->id, 'amount' => $amount, 'method' => 'cash', 'status' => 'paid'];
        $this->putJson("/api/v1/payments/{$first->id}", $body(70), $this->headers())->assertOk();
        $this->putJson("/api/v1/payments/{$first->id}", $body(70.01), $this->headers())->assertUnprocessable();
    }

    public function test_cancelled_orders_take_no_payments_but_refunds_still_save(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'subtotal' => 95, 'delivery_fee' => 5, 'total_amount' => 100]);
        $payment = Payment::create(['order_id' => $order->id, 'amount' => 100, 'method' => 'cash', 'status' => 'paid']);

        $order->update(['status' => 'cancelled']);

        $this->pay($order, 10)->assertUnprocessable()->assertJsonPath('errors.order_id.0', 'لا يمكن تسجيل دفعة على طلب ملغي');
        $this->assertSame('refunded', $payment->fresh()->status->value);
    }
}
