<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\AppUser;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $customer;
    protected array $p = [];
    protected Category $drinks;
    protected Category $coffee;
    protected Category $sweets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = AppUser::factory()->customer()->create();
        $this->drinks = Category::create(['name' => 'مشروبات']);
        $this->coffee = Category::create(['name' => 'قهوة', 'parent_category_id' => $this->drinks->id]);
        $this->sweets = Category::create(['name' => 'حلويات']);
        $warehouse = Warehouse::create(['name' => 'م']);
        $stock = app(StockService::class);

        $make = function (string $name, Category $cat, ?string $brand, array $sizes, int $stock_qty, array $extra = []) use ($warehouse, $stock) {
            $product = Product::create(['category_id' => $cat->id, 'name' => $name, 'brand' => $brand] + $extra);
            foreach ($sizes as $sizeName => [$price, $sku]) {
                $variant = ProductVariant::create(['product_id' => $product->id, 'name' => $sizeName, 'price' => $price, 'sku' => $sku]);
                if ($stock_qty > 0) {
                    $stock->receive($warehouse->id, $variant->id, $stock_qty);
                }
            }
            $this->travel(1)->seconds();

            return $product;
        };

        $this->p['arabic'] = $make('بن عربي', $this->coffee, 'الريف', ['250 جم' => [12, 'ARB-250'], '1 كجم' => [40, 'ARB-1KG']], 20, ['tags' => ['محمص']]);
        $this->p['espresso'] = $make('إسبريسو', $this->coffee, 'لافاتزا', ['1 كجم' => [65, 'ESP-1KG']], 0);
        $this->p['tea'] = $make('شاي أخضر', $this->drinks, 'ليبتون', ['25 كيس' => [6, 'TEA-25']], 30);
        $this->p['cake'] = $make('كيكة', $this->sweets, null, ['قالب' => [45, 'CAKE-1']], 5, ['description' => 'كيكة بطعم القهوة']);

        OrderItem::unguarded(function () {
            $order = \App\Models\Order::factory()->create();
            OrderItem::create(['order_id' => $order->id, 'product_variant_id' => $this->p['tea']->variants()->first()->id, 'product_name' => 'شاي', 'quantity' => 50, 'unit_price' => 6]);
            OrderItem::create(['order_id' => $order->id, 'product_variant_id' => $this->p['espresso']->variants()->first()->id, 'product_name' => 'إسبريسو', 'quantity' => 10, 'unit_price' => 65]);
        });
    }

    private function search(array $params)
    {
        $this->app['auth']->forgetGuards();

        return $this->getJson('/api/v1/cafe/products?' . http_build_query($params), ['Authorization' => 'Bearer ' . $this->customer->createToken('t')->plainTextToken])->assertOk();
    }

    private function names(array $params): array
    {
        return array_column($this->search($params)->json('data'), 'name');
    }

    public function test_text_search_covers_name_brand_description_tags_category_size_and_sku(): void
    {
        $this->assertSame(['بن عربي'], $this->names(['q' => 'عربي']));
        $this->assertSame(['إسبريسو'], $this->names(['q' => 'لافاتزا']));
        $this->assertSame(['بن عربي'], $this->names(['q' => 'محمص']));
        $this->assertEqualsCanonicalizing(['بن عربي', 'إسبريسو', 'كيكة'], $this->names(['q' => 'قهوة']));   // category + description
        $this->assertSame(['شاي أخضر'], $this->names(['search' => 'كيس']));                                  // legacy param + size name

        $res = $this->search(['q' => 'ARB-1KG']);
        $res->assertJsonPath('data.0.name', 'بن عربي')->assertJsonPath('data.0.matched_variant.name', '1 كجم')
            ->assertJsonPath('data.0.default_variant_id', $this->p['arabic']->variants()->where('sku', 'ARB-1KG')->value('id'));
    }

    public function test_relevance_puts_name_matches_first(): void
    {
        $this->assertSame('كيكة', $this->names(['q' => 'كيكة'])[0]);
        $this->assertSame('بن عربي', $this->names(['q' => 'بن'])[0]);
    }

    public function test_filters_combine_and_categories_include_children(): void
    {
        $this->assertEqualsCanonicalizing(['بن عربي', 'إسبريسو', 'شاي أخضر'], $this->names(['category_id' => $this->drinks->id]));
        $this->assertEqualsCanonicalizing(['بن عربي', 'إسبريسو', 'كيكة'], $this->names(['category_id' => "{$this->coffee->id},{$this->sweets->id}"]));
        $this->assertEqualsCanonicalizing(['بن عربي', 'شاي أخضر'], $this->names(['brand' => 'الريف,ليبتون']));
        $this->assertEqualsCanonicalizing(['بن عربي', 'شاي أخضر'], $this->names(['max_price' => 12]));
        $this->assertEqualsCanonicalizing(['بن عربي', 'إسبريسو', 'كيكة'], $this->names(['min_price' => 40, 'max_price' => 65]));
        $this->assertEqualsCanonicalizing(['بن عربي', 'شاي أخضر', 'كيكة'], $this->names(['in_stock' => 1]));
        $this->assertSame(['بن عربي'], $this->names(['category_id' => $this->coffee->id, 'in_stock' => 1, 'max_price' => 20]));
    }

    public function test_favorites_filter_and_card_fields(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/cafe/favorites', ['product_id' => $this->p['cake']->id], ['Authorization' => 'Bearer ' . $this->customer->createToken('t')->plainTextToken])->assertCreated();

        $res = $this->search(['favorites' => 1]);
        $res->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'كيكة')->assertJsonPath('data.0.is_favorite', true);

        $card = collect($this->search([])->json('data'))->firstWhere('name', 'بن عربي');
        $this->assertSame(['id', 'name', 'brand', 'image_url', 'image_type', 'min_price', 'max_price', 'in_stock', 'category_id', 'default_variant_id', 'is_favorite'], array_keys($card));
        $this->assertSame(['الريف', '12.00', '40.00', true], [$card['brand'], $card['min_price'], $card['max_price'], $card['in_stock']]);
        $this->assertFalse(collect($this->search([])->json('data'))->firstWhere('name', 'إسبريسو')['in_stock']);
    }

    public function test_sorting_and_pagination(): void
    {
        $this->assertSame(['شاي أخضر', 'بن عربي', 'كيكة', 'إسبريسو'], $this->names(['sort' => 'price_asc']));
        $this->assertSame(['إسبريسو', 'كيكة', 'بن عربي', 'شاي أخضر'], $this->names(['sort' => 'price_desc']));
        $this->assertSame(['كيكة', 'شاي أخضر', 'إسبريسو', 'بن عربي'], $this->names([]));                   // newest by default
        $this->assertSame(['شاي أخضر', 'إسبريسو'], array_slice($this->names(['sort' => 'popular']), 0, 2));

        $this->search(['sort' => 'price_asc', 'per_page' => 3, 'page' => 2])
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'إسبريسو')
            ->assertJsonPath('meta.total', 4)->assertJsonPath('meta.last_page', 2)->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.applied.sort', 'price_asc');
    }

    public function test_facets_count_each_option_without_its_own_filter(): void
    {
        $this->app['auth']->forgetGuards();
        $res = $this->getJson('/api/v1/cafe/products/filters?' . http_build_query(['category_id' => $this->coffee->id, 'in_stock' => 1]),
            ['Authorization' => 'Bearer ' . $this->customer->createToken('t')->plainTextToken])->assertOk();

        // Price range ignores the price filter but keeps the others (coffee + in stock = بن عربي only).
        $res->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.price.min', 12)->assertJsonPath('data.price.max', 40)
            ->assertJsonPath('data.in_stock_count', 1);

        $categories = collect($res->json('data.categories'))->pluck('count', 'name');
        $this->assertSame(['حلويات' => 1, 'قهوة' => 1, 'مشروبات' => 2], $categories->sortKeys()->all());   // in stock only, category filter ignored
        $brands = collect($res->json('data.brands'))->pluck('count', 'name');
        $this->assertSame(['الريف' => 1], $brands->all());                                                    // in stock + coffee
        $this->assertNotContains('relevance', collect($res->json('data.sort_options'))->pluck('value'));
    }

    public function test_search_ignores_hamza_taa_marbuta_and_harakat(): void
    {
        $category = Category::where('name', 'مشروبات')->first() ?? Category::create(['name' => 'مشروبات']);
        $product = Product::create(['name' => 'قهوة أمريكية', 'category_id' => $category->id, 'is_active' => true]);
        ProductVariant::create(['product_id' => $product->id, 'name' => 'وسط', 'price' => 10, 'is_active' => true]);

        foreach (['قهوه امريكيه', 'قهوة أمريكية', 'قَهْوة', 'امريكية'] as $term) {
            $this->assertNotNull(
                collect($this->search(['q' => $term])->json('data'))->firstWhere('name', 'قهوة أمريكية'),
                "لم يجد المنتج بالبحث عن: {$term}"
            );
        }
    }
}
