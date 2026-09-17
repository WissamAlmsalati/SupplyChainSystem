<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Models\Warehouse;
use App\Services\Payments\SandboxGateway;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $customer;
    protected AppUser $admin;
    protected AppUser $delegate;
    protected Address $address;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = AppUser::factory()->customer()->create(['mobile_number' => '0911111111']);
        $this->admin = AppUser::factory()->admin()->create();
        $this->delegate = AppUser::factory()->delegate()->create();

        $zone = DeliveryZone::create(['hex_id' => 'z1', 'delivery_price' => 5, 'is_active' => true]);
        $this->address = Address::create(['user_id' => $this->customer->id, 'name' => 'فرع', 'latitude' => 32.8, 'longitude' => 13.1, 'delivery_zone_id' => $zone->id]);
        $product = Product::create(['category_id' => Category::create(['name' => 'قهوة'])->id, 'name' => 'بن']);
        $this->variant = ProductVariant::create(['product_id' => $product->id, 'name' => '1 كجم', 'price' => 45]);
        app(StockService::class)->adjust(Warehouse::create(['name' => 'م'])->id, $this->variant->id, 50, StockMovementType::Adjustment);
    }

    private function as(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer ' . $user->createToken('t')->plainTextToken];
    }

    private function balance(): float
    {
        return (float) Wallet::where('user_id', $this->customer->id)->value('balance');
    }

    // Customer bank-transfer request with a receipt; returns the top-up id.
    private function requestTransfer(float $amount): int
    {
        return $this->post('/api/v1/customer/wallet/topups', [
            'amount' => $amount,
            'method' => 'bank_transfer',
            'reference_number' => 'TRX-' . random_int(1000, 9999),
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], $this->as($this->customer) + ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    }

    private function fund(float $amount): void
    {
        $id = $this->requestTransfer($amount);
        $this->postJson("/api/v1/wallet-topups/{$id}/approve", [], $this->as($this->admin))->assertOk();
    }

    private function placeOrder(string $method, int $qty = 1)
    {
        return $this->postJson('/api/v1/customer/orders', [
            'address_id' => $this->address->id,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => $qty]],
            'payment_method' => $method,
        ], $this->as($this->customer));
    }

    public function test_customer_gets_a_wallet_automatically(): void
    {
        $this->assertDatabaseHas('wallets', ['user_id' => $this->customer->id, 'balance' => 0]);
        $this->assertDatabaseMissing('wallets', ['user_id' => $this->admin->id]);

        $this->getJson('/api/v1/customer/wallet', $this->as($this->customer))
            ->assertOk()->assertJsonPath('data.balance', '0.00')->assertJsonPath('data.currency', 'LYD');
    }

    public function test_bank_transfer_request_is_credited_only_after_approval_and_only_once(): void
    {
        Storage::fake('public');

        $res = $this->post('/api/v1/customer/wallet/topups', [
            'amount' => 300, 'method' => 'bank_transfer', 'reference_number' => 'TRX-1',
            'receipt' => UploadedFile::fake()->image('r.jpg'),
        ], $this->as($this->customer) + ['Accept' => 'application/json'])->assertCreated();

        $topup = WalletTopup::find($res->json('data.id'));
        Storage::disk('public')->assertExists($topup->receipt_path);
        $this->assertSame(0.0, $this->balance());
        $this->assertDatabaseHas('notifications', ['type' => 'wallet', 'link' => "/wallet-topups/{$topup->id}"]);

        $this->postJson("/api/v1/wallet-topups/{$topup->id}/approve", [], $this->as($this->admin))->assertOk()->assertJsonPath('status', 'approved');
        $this->postJson("/api/v1/wallet-topups/{$topup->id}/approve", [], $this->as($this->admin))->assertUnprocessable();

        $this->assertSame(300.0, $this->balance());
        $this->assertDatabaseHas('wallet_transactions', ['type' => 'topup', 'amount' => 300, 'balance_after' => 300, 'created_by' => $this->admin->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->customer->id, 'title' => 'تم شحن محفظتك']);
    }

    public function test_bank_transfer_requires_a_receipt_image_or_pdf(): void
    {
        Storage::fake('public');
        $headers = $this->as($this->customer) + ['Accept' => 'application/json'];
        $base = ['amount' => 100, 'method' => 'bank_transfer', 'reference_number' => 'TRX-9'];

        $this->post('/api/v1/customer/wallet/topups', $base, $headers)->assertUnprocessable()->assertJsonValidationErrors('receipt');
        $this->post('/api/v1/customer/wallet/topups', $base + ['receipt' => UploadedFile::fake()->create('x.exe', 10, 'application/octet-stream')], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('receipt');

        $res = $this->post('/api/v1/customer/wallet/topups', $base + ['receipt' => UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf')], $headers)
            ->assertCreated()->assertJsonPath('data.receipt_type', 'pdf');
        Storage::disk('public')->assertExists(WalletTopup::find($res->json('data.id'))->receipt_path);

        $this->getJson("/api/v1/wallet-topups/{$res->json('data.id')}", $this->as($this->admin))
            ->assertOk()->assertJsonPath('receipt_type', 'pdf');
    }

    public function test_cash_at_office_and_other_methods_cannot_be_requested(): void
    {
        foreach (['cash', 'delegate_cash', 'gateway'] as $method) {
            $this->postJson('/api/v1/customer/wallet/topups', ['amount' => 100, 'method' => $method], $this->as($this->customer))
                ->assertUnprocessable()->assertJsonValidationErrors('method');
        }
    }

    public function test_bank_transfer_needs_reference_and_amount_limits_apply(): void
    {
        $this->postJson('/api/v1/customer/wallet/topups', ['amount' => 100, 'method' => 'bank_transfer'], $this->as($this->customer))->assertUnprocessable();
        $this->post('/api/v1/customer/wallet/topups', [
            'amount' => 1, 'method' => 'bank_transfer', 'reference_number' => 'TRX-1', 'receipt' => UploadedFile::fake()->image('r.jpg'),
        ], $this->as($this->customer) + ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_rejected_and_cancelled_requests_never_credit(): void
    {
        $a = $this->requestTransfer(100);
        $b = $this->requestTransfer(100);

        $this->postJson("/api/v1/wallet-topups/{$a}/reject", ['reason' => 'لم يصل التحويل'], $this->as($this->admin))->assertOk()->assertJsonPath('status', 'rejected');
        $this->postJson("/api/v1/customer/wallet/topups/{$b}/cancel", [], $this->as($this->customer))->assertOk();
        $this->postJson("/api/v1/wallet-topups/{$b}/approve", [], $this->as($this->admin))->assertUnprocessable();

        $this->assertSame(0.0, $this->balance());
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_gateway_checkout_credits_on_signed_callback_and_ignores_bad_or_repeated_ones(): void
    {
        $data = $this->postJson('/api/v1/customer/wallet/topups/gateway', ['amount' => 150], $this->as($this->customer))->assertCreated()->json('data');
        $token = WalletTopup::find($data['topup']['id'])->gateway_token;
        $this->assertStringContainsString($token, $data['checkout_url']);

        $this->get($data['checkout_url'])->assertOk()->assertSee('ادفع الآن');

        $this->postJson('/api/v1/wallet/gateway/callback', ['token' => $token, 'status' => 'paid', 'reference' => 'R1', 'signature' => 'forged'])->assertForbidden();
        $this->assertSame(0.0, $this->balance());

        $payload = ['token' => $token, 'status' => 'paid', 'reference' => 'R1', 'signature' => SandboxGateway::sign($token, 'paid', 'R1')];
        $this->postJson('/api/v1/wallet/gateway/callback', $payload)->assertOk()->assertJsonPath('status', 'approved');
        $this->postJson('/api/v1/wallet/gateway/callback', $payload)->assertOk();

        $this->assertSame(150.0, $this->balance());
        $this->assertDatabaseHas('wallet_topups', ['id' => $data['topup']['id'], 'gateway_reference' => 'R1']);
    }

    public function test_failed_gateway_payment_does_not_credit(): void
    {
        $id = $this->postJson('/api/v1/customer/wallet/topups/gateway', ['amount' => 150], $this->as($this->customer))->json('data.topup.id');
        $token = WalletTopup::find($id)->gateway_token;

        $this->postJson('/api/v1/wallet/gateway/callback', ['token' => $token, 'status' => 'failed', 'reference' => null, 'signature' => SandboxGateway::sign($token, 'failed', null)])
            ->assertOk()->assertJsonPath('status', 'failed');
        $this->assertSame(0.0, $this->balance());
    }

    public function test_delegate_cash_collection_credits_immediately(): void
    {
        $order = Order::factory()->create(['user_id' => $this->customer->id, 'delegate_id' => $this->delegate->id]);

        $this->postJson('/api/v1/delegate/wallet/collect', ['order_id' => $order->id, 'amount' => 200], $this->as($this->delegate))
            ->assertCreated()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.collected_by', $this->delegate->id);
        $this->postJson('/api/v1/delegate/wallet/collect', ['mobile_number' => '0911111111', 'amount' => 50], $this->as($this->delegate))->assertCreated();

        $this->assertSame(250.0, $this->balance());
        $this->getJson('/api/v1/delegate/wallet/collections', $this->as($this->delegate))->assertOk()->assertJsonPath('total', 250);

        $other = Order::factory()->create(['user_id' => $this->customer->id]);
        $this->postJson('/api/v1/delegate/wallet/collect', ['order_id' => $other->id, 'amount' => 50], $this->as($this->delegate))->assertNotFound();
        $this->postJson('/api/v1/delegate/wallet/collect', ['order_id' => $order->id, 'amount' => 50], $this->as($this->customer))->assertForbidden();
    }

    public function test_wallet_order_is_paid_in_full_and_short_balance_rolls_back_everything(): void
    {
        $this->fund(60);

        $this->placeOrder('wallet', 2)->assertUnprocessable()->assertJsonPath('required', 95);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(50, (int) \App\Models\Inventory::sum('quantity'));
        $this->assertSame(60.0, $this->balance());

        $this->placeOrder('wallet', 1)->assertCreated()->assertJsonPath('data.payment_method', 'wallet')->assertJsonPath('data.wallet_balance', '10.00');
        $this->assertSame(10.0, $this->balance());
        $this->assertDatabaseHas('payments', ['method' => 'wallet', 'status' => 'paid', 'amount' => 50]);
        $this->assertDatabaseHas('wallet_transactions', ['type' => 'payment', 'amount' => -50, 'balance_after' => 10]);
    }

    public function test_cancelling_a_wallet_order_refunds_once(): void
    {
        $this->fund(100);
        $orderId = $this->placeOrder('wallet')->json('data.id');

        $this->putJson("/api/v1/orders/{$orderId}", ['status' => 'cancelled'], $this->as($this->admin))->assertOk();
        $this->putJson("/api/v1/orders/{$orderId}", ['status' => 'cancelled'], $this->as($this->admin))->assertOk();

        $this->assertSame(100.0, $this->balance());
        $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'method' => 'wallet', 'status' => 'refunded']);
        $this->assertSame(1, \App\Models\WalletTransaction::where('type', 'refund')->count());
    }

    public function test_cash_orders_do_not_touch_the_wallet(): void
    {
        $this->fund(100);
        $this->placeOrder('cash')->assertCreated()->assertJsonPath('data.payment_method', 'cash');

        $this->assertSame(100.0, $this->balance());
        $this->assertDatabaseMissing('payments', ['method' => 'wallet']);
    }

    public function test_admin_adjustment_cannot_go_below_zero_and_is_audited(): void
    {
        $wallet = Wallet::where('user_id', $this->customer->id)->first();

        $this->postJson("/api/v1/wallets/{$wallet->id}/adjust", ['amount' => 40, 'note' => 'تعويض'], $this->as($this->admin))->assertOk();
        $this->postJson("/api/v1/wallets/{$wallet->id}/adjust", ['amount' => -41, 'note' => 'خطأ'], $this->as($this->admin))->assertUnprocessable();
        $this->postJson("/api/v1/wallets/{$wallet->id}/adjust", ['amount' => -15, 'note' => 'تصحيح'], $this->as($this->admin))->assertOk();
        $this->postJson("/api/v1/wallets/{$wallet->id}/adjust", ['amount' => 10], $this->as($this->admin))->assertUnprocessable();

        $this->assertSame(25.0, $this->balance());
        $this->getJson("/api/v1/wallets/{$wallet->id}/transactions", $this->as($this->admin))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/wallets', $this->as($this->admin))->assertOk()->assertJsonPath('meta.summary.total_balance', 25);
    }

    public function test_customer_cannot_use_admin_wallet_endpoints(): void
    {
        $wallet = Wallet::where('user_id', $this->customer->id)->first();
        \App\Models\Permission::firstOrCreate(['code' => 'WALLETS_EDIT']);

        $this->postJson("/api/v1/wallets/{$wallet->id}/adjust", ['amount' => 1000, 'note' => 'x'], $this->as($this->customer))->assertForbidden();
        $this->assertSame(0.0, $this->balance());
    }
}
