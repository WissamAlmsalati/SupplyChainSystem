<?php

namespace Tests\Feature;

use App\Enums\WalletTransactionType;
use App\Models\AppUser;
use App\Models\Order;
use App\Models\Payment;
use App\Services\WalletService;
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

    public function test_a_wallet_payment_takes_the_money_out_of_the_wallet(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'subtotal' => 95, 'delivery_fee' => 5, 'total_amount' => 100]);
        $wallets = app(WalletService::class);
        $body = ['order_id' => $order->id, 'amount' => 60, 'method' => 'wallet', 'status' => 'paid'];

        // No balance, no payment: the row alone would claim money that never moved.
        $this->postJson('/api/v1/payments', $body, $this->headers())->assertUnprocessable();
        $this->assertSame(0, Payment::count());

        $wallets->credit($wallets->walletFor($order->user), 100, WalletTransactionType::TopUp);
        $this->postJson('/api/v1/payments', $body, $this->headers())->assertCreated();

        $this->assertSame(40.0, $wallets->balance($order->user->fresh()));
        $this->assertDatabaseHas('wallet_transactions', ['type' => 'payment', 'amount' => -60]);
    }

    public function test_ledger_backed_payments_cannot_be_edited_or_deleted(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'subtotal' => 95, 'delivery_fee' => 5, 'total_amount' => 100]);
        $delegate = AppUser::factory()->delegate()->create();
        $collected = Payment::create(['order_id' => $order->id, 'amount' => 30, 'method' => 'cash', 'status' => 'paid', 'collected_by' => $delegate->id]);
        $office = Payment::create(['order_id' => $order->id, 'amount' => 20, 'method' => 'bank_transfer', 'status' => 'paid']);
        $body = fn (Payment $p, array $over = []) => $over + ['order_id' => $p->order_id, 'amount' => 10, 'method' => 'cash', 'status' => 'paid'];

        $this->patchJson("/api/v1/payments/{$collected->id}", $body($collected), $this->headers())->assertUnprocessable();
        $this->deleteJson("/api/v1/payments/{$collected->id}", [], $this->headers())->assertUnprocessable();

        // An office payment is just a row, so it can be corrected; but not moved, nor turned into a wallet one.
        $other = Order::factory()->create(['status' => 'confirmed', 'subtotal' => 95, 'delivery_fee' => 5, 'total_amount' => 100]);
        $this->patchJson("/api/v1/payments/{$office->id}", $body($office, ['order_id' => $other->id]), $this->headers())->assertUnprocessable();
        $this->patchJson("/api/v1/payments/{$office->id}", $body($office, ['method' => 'wallet']), $this->headers())->assertUnprocessable();
        $this->patchJson("/api/v1/payments/{$office->id}", $body($office), $this->headers())->assertOk();
        $this->postJson('/api/v1/payments', $body($office, ['status' => 'refunded']), $this->headers())->assertUnprocessable();
        $this->deleteJson("/api/v1/payments/{$office->id}", [], $this->headers())->assertNoContent();

        $this->assertDatabaseHas('activity_logs', ['entity_type' => 'Payment', 'action' => 'deleted']);
    }

    public function test_cancelling_returns_every_payment_to_the_wallet_whatever_the_method(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'subtotal' => 95, 'delivery_fee' => 5, 'total_amount' => 100]);
        Payment::create(['order_id' => $order->id, 'amount' => 70, 'method' => 'bank_transfer', 'status' => 'paid']);
        Payment::create(['order_id' => $order->id, 'amount' => 30, 'method' => 'card', 'status' => 'paid']);

        $order->update(['status' => 'cancelled']);

        $this->assertSame(100.0, app(WalletService::class)->balance($order->user->fresh()));
        $this->assertSame(0, $order->payments()->where('status', 'paid')->count());
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
