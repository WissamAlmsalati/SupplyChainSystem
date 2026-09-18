<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// A write that carries an Idempotency-Key happens once, however often it is sent.
class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private AppUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = AppUser::factory()->admin()->create();
    }

    private function headers(AppUser $user, ?string $key): array
    {
        $this->app['auth']->forgetGuards();

        return array_filter([
            'Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken,
            'Idempotency-Key' => $key,
        ]);
    }

    private function order(): Order
    {
        return Order::factory()->create(['status' => 'confirmed', 'subtotal' => 95, 'delivery_fee' => 5, 'total_amount' => 100]);
    }

    private function body(Order $order, float $amount): array
    {
        return ['order_id' => $order->id, 'amount' => $amount, 'method' => 'cash', 'status' => 'paid'];
    }

    public function test_a_repeat_with_the_same_key_answers_without_doing_the_work_again(): void
    {
        $order = $this->order();

        $first = $this->postJson('/api/v1/payments', $this->body($order, 40), $this->headers($this->admin, 'pay-1'))->assertCreated();
        $again = $this->postJson('/api/v1/payments', $this->body($order, 40), $this->headers($this->admin, 'pay-1'))->assertCreated();

        $this->assertSame(1, Payment::count());
        $this->assertSame($first->getContent(), $again->getContent());
        $again->assertHeader('Idempotent-Replay', 'true');
        $first->assertHeaderMissing('Idempotent-Replay');
    }

    public function test_without_a_key_nothing_changes(): void
    {
        $order = $this->order();

        $this->postJson('/api/v1/payments', $this->body($order, 40), $this->headers($this->admin, null))->assertCreated();
        $this->postJson('/api/v1/payments', $this->body($order, 40), $this->headers($this->admin, null))->assertCreated();

        $this->assertSame(2, Payment::count());
    }

    public function test_the_same_key_with_a_different_body_is_refused(): void
    {
        $order = $this->order();

        $this->postJson('/api/v1/payments', $this->body($order, 40), $this->headers($this->admin, 'pay-2'))->assertCreated();
        $this->postJson('/api/v1/payments', $this->body($order, 50), $this->headers($this->admin, 'pay-2'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'مفتاح الإتمام استُخدم من قبل مع بيانات مختلفة');

        $this->assertSame(1, Payment::count());
    }

    public function test_a_failed_attempt_is_not_remembered(): void
    {
        $order = $this->order();

        $this->postJson('/api/v1/payments', $this->body($order, 500), $this->headers($this->admin, 'pay-3'))->assertUnprocessable();
        // Same key, and the same body once the client has fixed nothing but the server state allows it.
        $order->update(['total_amount' => 500, 'subtotal' => 495]);
        $this->postJson('/api/v1/payments', $this->body($order, 500), $this->headers($this->admin, 'pay-3'))->assertCreated();
    }

    public function test_a_key_belongs_to_the_user_who_sent_it(): void
    {
        $order = $this->order();
        $other = AppUser::factory()->admin()->create();

        $this->postJson('/api/v1/payments', $this->body($order, 40), $this->headers($this->admin, 'shared'))->assertCreated();
        $this->postJson('/api/v1/payments', $this->body($order, 40), $this->headers($other, 'shared'))
            ->assertCreated()
            ->assertHeaderMissing('Idempotent-Replay');

        $this->assertSame(2, Payment::count());
    }

    public function test_an_oversized_key_is_refused(): void
    {
        $this->postJson('/api/v1/payments', $this->body($this->order(), 40), $this->headers($this->admin, str_repeat('k', 101)))
            ->assertUnprocessable();
    }
}
