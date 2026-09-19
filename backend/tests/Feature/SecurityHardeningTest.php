<?php

namespace Tests\Feature;

use App\Events\DelegateLocationUpdated;
use App\Models\ActivityLog;
use App\Models\AppUser;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PasswordResetOtp;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// What the security review found, held shut.
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function headers(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    private function variant(): ProductVariant
    {
        $product = Product::create(['category_id' => Category::create(['name' => 'قهوة'])->id, 'name' => 'بن']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => '1 كجم', 'price' => 7, 'cost_price' => 4.9]);
        Inventory::create(['warehouse_id' => Warehouse::factory()->create()->id, 'product_variant_id' => $variant->id, 'quantity' => 12]);

        return $variant;
    }

    public function test_cost_is_for_the_dashboard_only(): void
    {
        $variant = $this->variant();
        $path = "/api/v1/products/{$variant->product_id}";

        $guest = $this->getJson($path)->assertOk();
        $this->assertStringNotContainsString('cost_price', $guest->getContent());
        $this->assertStringNotContainsString('inventories', $guest->getContent());
        $guest->assertJsonPath('variants.0.in_stock', 12);

        $customer = AppUser::factory()->customer()->create();
        $this->assertStringNotContainsString('cost_price', $this->getJson("/api/v1/customer/products/{$variant->product_id}/variants", $this->headers($customer))->assertOk()->getContent());

        $order = Order::factory()->create(['user_id' => $customer->id]);
        OrderItem::create(['order_id' => $order->id, 'product_variant_id' => $variant->id, 'product_name' => 'بن', 'quantity' => 1, 'unit_price' => 7, 'unit_cost' => 4.9]);
        $mine = $this->getJson("/api/v1/customer/orders/{$order->id}", $this->headers($customer))->assertOk()->getContent();
        $this->assertStringNotContainsString('unit_cost', $mine);
        $this->assertStringNotContainsString('cost_price', $mine);

        $admin = $this->getJson($path, $this->headers(AppUser::factory()->admin()->create()))->assertOk();
        $admin->assertJsonPath('variants.0.cost_price', '4.90');
        $this->assertStringContainsString('inventories', $admin->getContent());
    }

    public function test_the_apps_cannot_call_dashboard_routes_whatever_codes_their_roles_hold(): void
    {
        $customer = AppUser::factory()->customer()->create();
        $delegate = AppUser::factory()->delegate()->create();
        $foreign = Order::factory()->create();

        foreach ([$customer, $delegate] as $user) {
            $headers = $this->headers($user);
            $this->getJson('/api/v1/orders', $headers)->assertForbidden();
            $this->getJson("/api/v1/orders/{$foreign->id}", $headers)->assertForbidden();
            $this->get("/api/v1/orders/{$foreign->id}/invoice", $headers)->assertForbidden();
            $this->postJson("/api/v1/orders/{$foreign->id}/assign-delegate", ['delegate_id' => $delegate->id], $headers)->assertForbidden();
            $this->getJson('/api/v1/inventory', $headers)->assertForbidden();
        }

        // Their own doors still open.
        $this->getJson('/api/v1/customer/orders', $this->headers($customer))->assertOk();
        $this->getJson('/api/v1/delegate/orders', $this->headers($delegate))->assertOk();
        $this->getJson('/api/v1/me', $this->headers($customer))->assertOk();
    }

    public function test_switching_an_account_off_ends_its_sessions_at_once(): void
    {
        $delegate = AppUser::factory()->delegate()->create();
        $headers = $this->headers($delegate);
        $this->getJson('/api/v1/delegate/orders', $headers)->assertOk();

        $delegate->update(['is_active' => false]);

        $this->assertSame(0, $delegate->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/delegate/orders', $headers)->assertUnauthorized();

        // Even a token minted afterwards is refused while the account is off.
        $this->getJson('/api/v1/delegate/orders', $this->headers($delegate))->assertForbidden();
    }

    public function test_changing_a_password_ends_the_other_sessions(): void
    {
        $user = AppUser::factory()->customer()->create();
        $user->createToken('old phone');
        $user->createToken('old tablet');

        $user->update(['password' => Hash::make('a-new-password')]);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_no_password_is_stored_or_logged_in_a_readable_form(): void
    {
        $this->postJson('/api/v1/customer/register', [
            'name' => 'مقهى جديد', 'phone_number' => '0911112222', 'password' => 'secret-123', 'password_confirmation' => 'secret-123',
            'latitude' => 32.88, 'longitude' => 13.19,
        ])->assertCreated();

        $stored = PasswordResetOtp::latest('id')->first()->payload['password'];
        $this->assertNotSame('secret-123', $stored);
        $this->assertTrue(Hash::check('secret-123', $stored));

        $admin = AppUser::factory()->admin()->create();
        $this->actingAs($admin);
        $admin->update(['password' => Hash::make('another-one'), 'name' => 'اسم جديد']);

        $log = ActivityLog::where('entity_type', 'AppUser')->where('action', 'updated')->latest('id')->first();
        $this->assertStringNotContainsString('$2y$', json_encode($log->metadata));
        $this->assertSame('اسم جديد', $log->metadata['changes']['name']);
        $this->assertArrayHasKey('name', $log->metadata['before']);
    }

    public function test_driver_positions_are_a_private_channel_for_staff_who_may_see_delegates(): void
    {
        // The suite's null broadcaster authorises everything, so use the real
        // one (it only signs the answer; nothing goes over the network).
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => '1']);
        require base_path('routes/channels.php');

        $body = ['socket_id' => '1234.5678', 'channel_name' => 'private-delegates.locations'];

        $this->postJson('/api/v1/broadcasting/auth', $body)->assertUnauthorized();
        $this->postJson('/api/v1/broadcasting/auth', $body, $this->headers(AppUser::factory()->customer()->create()))->assertForbidden();
        $this->postJson('/api/v1/broadcasting/auth', $body, $this->headers(AppUser::factory()->delegate()->create()))->assertForbidden();
        $this->postJson('/api/v1/broadcasting/auth', $body, $this->headers(AppUser::factory()->admin()->create()))->assertOk();

        $this->assertInstanceOf(PrivateChannel::class, (new DelegateLocationUpdated(AppUser::factory()->delegate()->create()))->broadcastOn()[0]);
    }
}
