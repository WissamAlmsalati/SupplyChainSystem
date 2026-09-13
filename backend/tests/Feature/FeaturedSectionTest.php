<?php

namespace Tests\Feature;

use App\Models\AppUser;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AppUser::factory()->admin()->create();
        $this->customer = AppUser::factory()->customer()->create();
        $category = Category::create(['name' => 'قهوة']);

        foreach (['بن عربي', 'إسبريسو', 'شاي', 'سكر'] as $i => $name) {
            $product = Product::create(['category_id' => $category->id, 'name' => $name]);
            ProductVariant::create(['product_id' => $product->id, 'name' => 'صغير', 'price' => 10 + $i]);
            ProductVariant::create(['product_id' => $product->id, 'name' => 'كبير', 'price' => 20 + $i]);
            $this->products[$name] = $product;
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

        $this->getJson('/api/v1/cafe/featured-sections', $this->as($this->customer))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'الأكثر طلباً')
            ->assertJsonPath('data.0.products.0.name', 'إسبريسو')
            ->assertJsonPath('data.0.products.0.min_price', '11.00')
            ->assertJsonPath('data.0.products.1.name', 'بن عربي')
            ->assertJsonStructure(['data' => [['id', 'title', 'products' => [['id', 'name', 'image_url', 'min_price', 'category_id', 'default_variant_id']]]]])
            ->assertJsonPath('data.1.title', 'مستلزمات');

        $this->postJson('/api/v1/featured-sections/reorder', ['ids' => [$second->id, $first->id]], $this->as($this->admin))->assertOk();
        $this->getJson('/api/v1/cafe/featured-sections', $this->as($this->customer))->assertJsonPath('data.0.title', 'مستلزمات');
    }

    public function test_unavailable_products_are_hidden_and_empty_sections_skipped(): void
    {
        $section = FeaturedSection::create(['title' => 'عروض']);
        $section->syncProducts($this->ids('شاي', 'سكر'));
        $this->products['شاي']->update(['is_active' => false]);
        $this->products['سكر']->variants()->update(['is_active' => false]);

        $this->getJson('/api/v1/cafe/featured-sections', $this->as($this->customer))->assertOk()->assertJsonCount(0, 'data');

        $this->products['سكر']->variants()->update(['is_active' => true]);
        $this->getJson('/api/v1/cafe/featured-sections', $this->as($this->customer))
            ->assertJsonCount(1, 'data.0.products')->assertJsonPath('data.0.products.0.name', 'سكر');
    }

    public function test_customers_cannot_manage_sections(): void
    {
        \App\Models\Permission::create(['code' => 'FEATURED_SECTIONS_CREATE']);
        $this->postJson('/api/v1/featured-sections', ['title' => 'x', 'product_ids' => $this->ids('شاي')], $this->as($this->customer))->assertForbidden();
    }
}
