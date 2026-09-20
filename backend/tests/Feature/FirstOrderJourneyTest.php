<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Inventory;
use App\Models\PremiumFeature;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\AddressZoneResolver;
use App\Services\H3Service;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// A cafe that has just registered must be able to reach its first order with
// every feature flag at its default, and the fee must come from the server.
class FirstOrderJourneyTest extends TestCase
{
    use RefreshDatabase;

    // 32.88, 13.19 (Tripoli) falls in this res-4 cell.
    private const TRIPOLI = '84384b3ffffffff';

    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zone = DeliveryZone::create(['hex_id' => self::TRIPOLI, 'name' => 'طرابلس', 'delivery_price' => 7, 'is_active' => true]);
        PremiumFeature::updateOrCreate(['code' => 'customer_branches'], ['name' => 'branches', 'is_active' => false]);
        PremiumFeature::updateOrCreate(['code' => 'customer_auto_approve'], ['name' => 'auto', 'is_active' => true]);
    }

    private function headers(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    public function test_register_verify_and_order_with_default_flags(): void
    {
        $register = $this->postJson('/api/v1/customer/register', [
            'name' => 'مقهى الفجر', 'phone_number' => '0913334444', 'password' => 'secret-123', 'password_confirmation' => 'secret-123',
            'latitude' => 32.88, 'longitude' => 13.19,
        ])->assertCreated();
        $this->postJson('/api/v1/customer/verify-otp', ['token' => $register->json('data.token'), 'otp' => (string) $register->json('data.otp')])->assertSuccessful();

        $user = AppUser::where('mobile_number', '0913334444')->firstOrFail();
        $address = Address::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($this->zone->id, $address->delivery_zone_id);

        $product = Product::create(['category_id' => Category::create(['name' => 'قهوة'])->id, 'name' => 'بن']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => '1 كجم', 'price' => 45]);
        app(StockService::class)->adjust(Warehouse::create(['name' => 'م'])->id, $variant->id, 10, StockMovementType::Adjustment);

        $this->postJson('/api/v1/customer/orders', [
            'address_id' => $address->id, 'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]], 'payment_method' => 'cash',
        ], $this->headers($user))->assertCreated();
        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'delivery_fee' => 7, 'total_amount' => 52]);
    }

    public function test_the_first_address_is_always_allowed_and_more_need_the_feature(): void
    {
        $user = AppUser::factory()->customer()->create();
        $body = ['name' => 'الفرع الأول', 'latitude' => 32.88, 'longitude' => 13.19];

        $this->getJson('/api/v1/customer/profile', $this->headers($user))->assertOk()->assertJsonPath('can_add_address', true);
        $this->postJson('/api/v1/customer/addresses', $body, $this->headers($user))->assertCreated();
        $this->getJson('/api/v1/customer/profile', $this->headers($user))->assertJsonPath('can_add_address', false);
        $this->postJson('/api/v1/customer/addresses', ['name' => 'فرع ثان'] + $body, $this->headers($user))->assertForbidden();

        PremiumFeature::where('code', 'customer_branches')->update(['is_active' => true]);
        $this->postJson('/api/v1/customer/addresses', ['name' => 'فرع ثان'] + $body, $this->headers($user))->assertCreated();
    }

    public function test_the_server_picks_the_zone_whatever_the_client_sends(): void
    {
        $user = AppUser::factory()->customer()->create();
        $cheap = DeliveryZone::create(['hex_id' => '842da29ffffffff', 'name' => 'رخيصة', 'delivery_price' => 0, 'is_active' => true]);

        $this->postJson('/api/v1/customer/addresses', [
            'name' => 'فرع', 'latitude' => 32.88, 'longitude' => 13.19, 'delivery_zone_id' => $cheap->id,
        ], $this->headers($user))->assertCreated();
        $id = Address::where('user_id', $user->id)->value('id');
        $this->assertSame($this->zone->id, Address::find($id)->delivery_zone_id);

    }

    public function test_an_address_outside_coverage_is_saved_with_its_own_status_and_cannot_be_ordered_to(): void
    {
        $user = AppUser::factory()->customer()->create();
        PremiumFeature::where('code', 'customer_branches')->update(['is_active' => true]);

        // 25.0, 20.0 is deep in the desert: saved, answered 202, ready for a dialog.
        $res = $this->postJson('/api/v1/customer/addresses', ['name' => 'فرع الجنوب', 'latitude' => 25.0, 'longitude' => 20.0], $this->headers($user))
            ->assertStatus(202)
            ->assertJsonPath('code', 'address_outside_coverage')
            ->assertJsonPath('title', 'عنوانك خارج نطاق التوصيل حالياً')
            ->assertJsonPath('data.is_deliverable', false)
            ->assertJsonPath('data.delivery_zone', null);
        $this->assertStringContainsString('سنرسل لك إشعاراً', $res->json('message'));
        $far = Address::findOrFail($res->json('data.id'));

        // Inside a zone it is the ordinary 201.
        $near = $this->postJson('/api/v1/customer/addresses', ['name' => 'فرع طرابلس', 'latitude' => 32.88, 'longitude' => 13.19], $this->headers($user))
            ->assertCreated()->assertJsonPath('code', 'address_saved')->assertJsonPath('data.is_deliverable', true)->json('data.id');

        // Moving a good pin out of coverage answers 202 too; the list says which can be used.
        $this->patchJson("/api/v1/customer/addresses/{$near}", ['latitude' => 25.0, 'longitude' => 20.0], $this->headers($user))->assertStatus(202);
        $this->patchJson("/api/v1/customer/addresses/{$near}", ['latitude' => 32.88, 'longitude' => 13.19], $this->headers($user))->assertOk()->assertJsonPath('data.is_deliverable', true);
        $list = collect($this->getJson('/api/v1/customer/addresses', $this->headers($user))->assertOk()->json('data.addresses'))->keyBy('id');
        $this->assertFalse($list[$far->id]['is_deliverable']);
        $this->assertTrue($list[$near]['is_deliverable']);

        // It cannot be ordered to, from the cart or directly, and nothing leaves the shelf.
        $product = Product::create(['category_id' => Category::create(['name' => 'قهوة'])->id, 'name' => 'بن']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => '1 كجم', 'price' => 45]);
        app(StockService::class)->adjust(Warehouse::create(['name' => 'م'])->id, $variant->id, 10, StockMovementType::Adjustment);
        $this->postJson('/api/v1/customer/orders', ['address_id' => $far->id, 'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]]], $this->headers($user))
            ->assertUnprocessable()->assertJsonValidationErrors('address_id');
        $this->postJson('/api/v1/customer/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1], $this->headers($user))->assertSuccessful();
        $this->postJson('/api/v1/customer/cart/checkout', ['address_id' => $far->id], $this->headers($user))->assertUnprocessable();
        $this->assertSame(10, (int) Inventory::sum('quantity'));
    }

    public function test_the_cafe_is_told_the_day_delivery_reaches_its_address(): void
    {
        $user = AppUser::factory()->customer()->create();
        // Benghazi, which no zone covers yet.
        $address = Address::create(['user_id' => $user->id, 'name' => 'فرع بنغازي', 'latitude' => 32.1167, 'longitude' => 20.0667]);
        $cell = H3Service::latLngToCell(32.1167, 20.0667, 4);

        // A zone somewhere else changes nothing; an inactive one over it changes nothing either.
        DeliveryZone::create(['hex_id' => '842da29ffffffff', 'name' => 'بعيدة', 'delivery_price' => 5, 'is_active' => true]);
        $zone = DeliveryZone::create(['hex_id' => $cell, 'name' => 'بنغازي', 'delivery_price' => 9, 'is_active' => false]);
        $this->assertNull($address->fresh()->delivery_zone_id);

        $zone->update(['is_active' => true]);

        $this->assertSame($zone->id, $address->fresh()->delivery_zone_id);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'title' => 'بدأنا التوصيل إلى منطقتك', 'entity_type' => 'address', 'entity_id' => $address->id]);
        $this->getJson('/api/v1/customer/addresses', $this->headers($user))->assertOk()->assertJsonPath('data.addresses.0.is_deliverable', true);
    }

    public function test_a_hexagon_service_that_is_down_costs_a_sentence_not_a_500(): void
    {
        $this->app->bind(AddressZoneResolver::class, fn () => new class extends AddressZoneResolver
        {
            public function resolve(float $latitude, float $longitude): ?DeliveryZone
            {
                throw new \RuntimeException('H3 service failed: node: not found');
            }
        });
        $user = AppUser::factory()->customer()->create();

        $this->postJson('/api/v1/customer/addresses', ['name' => 'فرع', 'latitude' => 32.88, 'longitude' => 13.19], $this->headers($user))
            ->assertUnprocessable()->assertJsonPath('errors.latitude.0', 'تعذّر تحديد منطقة التوصيل الآن، حاول بعد قليل');
    }

    public function test_an_app_order_to_an_uncovered_address_is_refused_rather_than_shipped_free(): void
    {
        $user = AppUser::factory()->customer()->create();
        $this->zone->update(['is_active' => false]);
        $address = Address::create(['user_id' => $user->id, 'name' => 'فرع', 'latitude' => 32.88, 'longitude' => 13.19, 'delivery_zone_id' => $this->zone->id]);
        $product = Product::create(['category_id' => Category::create(['name' => 'قهوة'])->id, 'name' => 'بن']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => '1 كجم', 'price' => 45]);
        app(StockService::class)->adjust(Warehouse::create(['name' => 'م'])->id, $variant->id, 10, StockMovementType::Adjustment);

        $this->postJson('/api/v1/customer/orders', [
            'address_id' => $address->id, 'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]], 'payment_method' => 'cash',
        ], $this->headers($user))->assertUnprocessable()->assertJsonPath('errors.address_id.0', 'هذا العنوان خارج نطاق التوصيل حالياً، اختر عنواناً آخر. سنبلغك فور بدء التوصيل إلى منطقتك.');
        $this->assertSame(10, (int) Inventory::sum('quantity'));
    }
}
