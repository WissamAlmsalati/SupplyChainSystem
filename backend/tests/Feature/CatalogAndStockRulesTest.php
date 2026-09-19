<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAndStockRulesTest extends TestCase
{
    use RefreshDatabase;

    private function headers(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    public function test_stock_leaves_the_warehouse_that_serves_the_zone_and_the_order_says_where_to_load(): void
    {
        $tripoli = Warehouse::create(['name' => 'طرابلس']);
        $benghazi = Warehouse::create(['name' => 'بنغازي']);
        $zone = DeliveryZone::create(['hex_id' => 'z-b', 'name' => 'بنغازي', 'delivery_price' => 5, 'is_active' => true, 'warehouse_id' => $benghazi->id]);
        $customer = AppUser::factory()->customer()->create();
        $address = Address::create(['user_id' => $customer->id, 'name' => 'فرع', 'latitude' => 32.1, 'longitude' => 20.0, 'delivery_zone_id' => $zone->id]);
        $product = Product::create(['category_id' => Category::create(['name' => 'قهوة'])->id, 'name' => 'بن']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => '1 كجم', 'price' => 45]);
        $stock = app(StockService::class);
        $stock->adjust($tripoli->id, $variant->id, 10, StockMovementType::Adjustment);
        $stock->adjust($benghazi->id, $variant->id, 3, StockMovementType::Adjustment);

        $order = fn (int $qty) => $this->postJson('/api/v1/customer/orders', [
            'address_id' => $address->id, 'items' => [['product_variant_id' => $variant->id, 'quantity' => $qty]],
        ], $this->headers($customer))->assertCreated();

        // Benghazi first, although Tripoli has the lower id and more stock.
        $order(2);
        $this->assertSame(1, Inventory::where('warehouse_id', $benghazi->id)->value('quantity'));
        $this->assertSame(10, Inventory::where('warehouse_id', $tripoli->id)->value('quantity'));
        $this->assertSame($benghazi->id, Order::latest('id')->first()->warehouse_id);

        // What Benghazi lacks comes from Tripoli, rather than refusing the order.
        $order(4);
        $this->assertSame(0, Inventory::where('warehouse_id', $benghazi->id)->value('quantity'));
        $this->assertSame(7, Inventory::where('warehouse_id', $tripoli->id)->value('quantity'));
    }

    public function test_a_size_needs_a_price_and_cannot_be_created_twice_under_another_spelling(): void
    {
        $admin = AppUser::factory()->admin()->create();
        $product = Product::create(['category_id' => Category::create(['name' => 'ألبان'])->id, 'name' => 'حليب']);
        $body = fn (array $over = []) => $over + ['product_id' => $product->id, 'name' => '250 جم', 'price' => 12, 'is_active' => true];

        $this->postJson('/api/v1/product-variants', $body(['price' => 0]), $this->headers($admin))->assertUnprocessable()->assertJsonValidationErrors('price');
        $this->postJson('/api/v1/product-variants', $body(), $this->headers($admin))->assertCreated();
        $this->postJson('/api/v1/product-variants', $body(['name' => '٢٥٠جم']), $this->headers($admin))->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/product-variants', $body(['name' => '500 جم']), $this->headers($admin))->assertCreated();
    }

    public function test_a_hidden_product_cannot_be_ordered_through_its_sizes(): void
    {
        $customer = AppUser::factory()->customer()->create();
        $zone = DeliveryZone::create(['hex_id' => 'z1', 'delivery_price' => 5, 'is_active' => true]);
        $address = Address::create(['user_id' => $customer->id, 'name' => 'فرع', 'latitude' => 32.8, 'longitude' => 13.1, 'delivery_zone_id' => $zone->id]);
        $product = Product::create(['category_id' => Category::create(['name' => 'قهوة'])->id, 'name' => 'بن موقوف', 'is_active' => false]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => '1 كجم', 'price' => 45, 'is_active' => true]);
        app(StockService::class)->adjust(Warehouse::create(['name' => 'م'])->id, $variant->id, 10, StockMovementType::Adjustment);

        $this->postJson('/api/v1/customer/orders', ['address_id' => $address->id, 'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]]], $this->headers($customer))->assertUnprocessable();
        $this->postJson('/api/v1/customer/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1], $this->headers($customer))->assertUnprocessable();
        $this->assertSame(10, (int) Inventory::sum('quantity'));
    }
}
