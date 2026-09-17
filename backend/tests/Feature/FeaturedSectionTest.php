<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\AppUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Models\Category;
use App\Models\FeaturedSection;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedSectionTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $admin;
    protected AppUser $customer;
    protected array $products = [];
    protected Category $category;
    protected Category $sweets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AppUser::factory()->admin()->create();
        $this->customer = AppUser::factory()->customer()->create();
        $category = Category::create(['name' => 'قهوة']);

        $this->category = $category;
        $this->sweets = Category::create(['name' => 'حلويات']);
        $warehouse = Warehouse::create(['name' => 'م']);
        foreach (['بن عربي', 'إسبريسو', 'شاي', 'سكر'] as $i => $name) {
            $product = Product::create(['category_id' => $name === 'سكر' ? $this->sweets->id : $category->id, 'name' => $name]);
            $small = ProductVariant::create(['product_id' => $product->id, 'name' => 'صغير', 'price' => 10 + $i]);
            ProductVariant::create(['product_id' => $product->id, 'name' => 'كبير', 'price' => 20 + $i]);
            if ($name !== 'إسبريسو') {
                app(StockService::class)->receive($warehouse->id, $small->id, 10);
            }
            $this->products[$name] = $product;
        }

        // Sales: شاي 30, بن عربي 20, سكر 5, إسبريسو 0
        $order = Order::factory()->create();
        foreach (['شاي' => 30, 'بن عربي' => 20, 'سكر' => 5] as $name => $qty) {
            OrderItem::create(['order_id' => $order->id, 'product_variant_id' => $this->products[$name]->variants()->first()->id, 'product_name' => $name, 'quantity' => $qty, 'unit_price' => 1]);
        }
    }

    private function as(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer ' . $user->createToken('t')->plainTextToken];
    }

    private function ids(string ...$names): array
    {
        return array_map(fn ($n) => $this->products[$n]->id, $names);
    }

    public function test_admin_manages_sections_and_product_order(): void
    {
        $id = $this->postJson('/api/v1/featured-sections', [
            'title' => 'الأكثر طلباً',
            'product_ids' => $this->ids('شاي', 'بن عربي', 'سكر'),
        ], $this->as($this->admin))->assertCreated()
            ->assertJsonPath('products.0.name', 'شاي')
            ->assertJsonPath('products.2.name', 'سكر')
            ->json('id');

        $this->putJson("/api/v1/featured-sections/{$id}", [
            'title' => 'مختاراتنا',
            'product_ids' => $this->ids('سكر', 'إسبريسو'),
        ], $this->as($this->admin))->assertOk()
            ->assertJsonPath('title', 'مختاراتنا')
            ->assertJsonCount(2, 'products')
            ->assertJsonPath('products.0.name', 'سكر');

        $this->postJson('/api/v1/featured-sections', ['title' => 'x', 'product_ids' => [999]], $this->as($this->admin))->assertUnprocessable();
        $this->getJson('/api/v1/featured-sections', $this->as($this->admin))->assertOk()->assertJsonPath('data.0.products_count', 2);

        $this->deleteJson("/api/v1/featured-sections/{$id}", [], $this->as($this->admin))->assertNoContent();
        $this->assertDatabaseCount('featured_section_product', 0);
    }

    public function test_app_endpoint_returns_title_and_products_in_one_object_in_display_order(): void
    {
        $second = FeaturedSection::create(['title' => 'مستلزمات', 'sort_order' => 1]);
        $second->syncProducts($this->ids('سكر'));
        $first = FeaturedSection::create(['title' => 'الأكثر طلباً', 'sort_order' => 0]);
        $first->syncProducts($this->ids('إسبريسو', 'بن عربي'));
        FeaturedSection::create(['title' => 'مخفي', 'is_active' => false])->syncProducts($this->ids('شاي'));

        $this->getJson('/api/v1/customer/featured-sections', $this->as($this->customer))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'الأكثر طلباً')
            ->assertJsonPath('data.0.products.0.name', 'إسبريسو')
            ->assertJsonPath('data.0.products.0.min_price', '11.00')
            ->assertJsonPath('data.0.products.1.name', 'بن عربي')
            ->assertJsonStructure(['data' => [['id', 'title', 'source', 'sort', 'products_total', 'has_more', 'products' => [['id', 'name', 'image_url', 'min_price', 'category_id', 'default_variant_id']]]], 'meta' => ['current_page', 'per_page', 'total', 'last_page']])
            ->assertJsonPath('data.1.title', 'مستلزمات');

        $this->postJson('/api/v1/featured-sections/reorder', ['ids' => [$second->id, $first->id]], $this->as($this->admin))->assertOk();
        $this->getJson('/api/v1/customer/featured-sections', $this->as($this->customer))->assertJsonPath('data.0.title', 'مستلزمات');
    }

    public function test_unavailable_products_are_hidden_and_empty_sections_skipped(): void
    {
        $section = FeaturedSection::create(['title' => 'عروض']);
        $section->syncProducts($this->ids('شاي', 'سكر'));
        $this->products['شاي']->update(['is_active' => false]);
        $this->products['سكر']->variants()->update(['is_active' => false]);

        $this->getJson('/api/v1/customer/featured-sections', $this->as($this->customer))->assertOk()->assertJsonCount(0, 'data');

        $this->products['سكر']->variants()->update(['is_active' => true]);
        $this->getJson('/api/v1/customer/featured-sections', $this->as($this->customer))
            ->assertJsonCount(1, 'data.0.products')->assertJsonPath('data.0.products.0.name', 'سكر');
    }

    public function test_customers_cannot_manage_sections(): void
    {
        \App\Models\Permission::firstOrCreate(['code' => 'FEATURED_SECTIONS_CREATE']);
        $this->postJson('/api/v1/featured-sections', ['title' => 'x', 'product_ids' => $this->ids('شاي')], $this->as($this->customer))->assertForbidden();
    }

    public function test_rule_based_sections_pick_products_by_sort_and_filters(): void
    {
        $h = $this->as($this->admin);

        $this->postJson('/api/v1/featured-sections', ['title' => 'الأكثر مبيعاً', 'source' => 'filter', 'sort' => 'popular', 'products_limit' => 3], $h)
            ->assertCreated()
            ->assertJsonPath('source', 'filter')
            ->assertJsonPath('products_total', 4)
            ->assertJsonPath('products.0.name', 'شاي')
            ->assertJsonPath('products.1.name', 'بن عربي')
            ->assertJsonPath('products.0.sold_quantity', 30);

        $this->postJson('/api/v1/featured-sections', [
            'title' => 'مشروبات رخيصة ومتوفرة', 'source' => 'filter', 'sort' => 'price_asc',
            'filters' => ['category_id' => [$this->category->id], 'in_stock' => true, 'max_price' => 12],
        ], $h)->assertCreated()->assertJsonPath('products_total', 2)->assertJsonPath('products.0.name', 'بن عربي');

        $this->postJson('/api/v1/featured-sections', ['title' => 'x', 'source' => 'filter'], $h)->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->postJson('/api/v1/featured-sections', ['title' => 'x', 'source' => 'filter', 'sort' => 'relevance'], $h)->assertUnprocessable();
        $this->postJson('/api/v1/featured-sections/preview', ['sort' => 'price_desc', 'products_limit' => 2], $h)
            ->assertOk()->assertJsonPath('total', 4)->assertJsonCount(2, 'products')->assertJsonPath('products.0.name', 'سكر');

        $app = $this->getJson('/api/v1/customer/featured-sections', $this->as($this->customer))->assertOk();
        $best = collect($app->json('data'))->firstWhere('title', 'الأكثر مبيعاً');
        $this->assertSame(['شاي', 'بن عربي', 'سكر'], array_column($best['products'], 'name'));
        $this->assertSame([4, true, 'popular'], [$best['products_total'], $best['has_more'], $best['sort']]);
    }

    public function test_rule_sections_update_automatically_with_new_sales(): void
    {
        $section = FeaturedSection::create(['title' => 'الأكثر مبيعاً', 'source' => 'filter', 'sort' => 'popular']);
        $first = fn () => $this->getJson("/api/v1/customer/featured-sections/{$section->id}/products", $this->as($this->customer))->json('data.0.name');
        $this->assertSame('شاي', $first());

        OrderItem::create(['order_id' => Order::factory()->create()->id, 'product_variant_id' => $this->products['سكر']->variants()->first()->id, 'product_name' => 'سكر', 'quantity' => 100, 'unit_price' => 1]);
        $this->assertSame('سكر', $first());
    }

    public function test_section_products_endpoint_and_section_list_are_paginated(): void
    {
        foreach (range(1, 3) as $i) {
            FeaturedSection::create(['title' => "قسم {$i}", 'source' => 'filter', 'sort' => 'name_asc', 'products_limit' => 2, 'sort_order' => $i]);
        }
        $h = $this->as($this->customer);

        $this->getJson('/api/v1/customer/featured-sections?per_page=2&page=2', $h)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'قسم 3')
            ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data.0.products')->assertJsonPath('data.0.has_more', true);

        $id = FeaturedSection::where('title', 'قسم 1')->value('id');
        $this->getJson("/api/v1/customer/featured-sections/{$id}/products?per_page=3&page=2", $h)->assertOk()
            ->assertJsonPath('section.title', 'قسم 1')->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 4)->assertJsonPath('meta.last_page', 2);

        FeaturedSection::whereKey($id)->update(['is_active' => false]);
        $this->getJson("/api/v1/customer/featured-sections/{$id}/products", $h)->assertNotFound();
    }
}
