<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Category;
use App\Models\FeaturedSection;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $customer;
    protected AppUser $other;
    protected Product $coffee;
    protected Product $tea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = AppUser::factory()->customer()->create();
        $this->other = AppUser::factory()->customer()->create();
        $category = Category::create(['name' => 'مشروبات']);
        $this->coffee = Product::create(['category_id' => $category->id, 'name' => 'قهوة']);
        ProductVariant::create(['product_id' => $this->coffee->id, 'name' => '1 كجم', 'price' => 40]);
        $this->tea = Product::create(['category_id' => $category->id, 'name' => 'شاي']);
        ProductVariant::create(['product_id' => $this->tea->id, 'name' => 'علبة', 'price' => 9]);
    }

    private function as(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer ' . $user->createToken('t')->plainTextToken];
    }

    public function test_customer_adds_lists_and_removes_favorites_idempotently(): void
    {
        $h = $this->as($this->customer);

        $this->postJson('/api/v1/cafe/favorites', ['product_id' => $this->tea->id], $h)->assertCreated()->assertJsonPath('data.favorites_count', 1);
        $this->travel(1)->seconds();
        $this->postJson('/api/v1/cafe/favorites', ['product_id' => $this->coffee->id], $h)->assertCreated();
        $this->postJson('/api/v1/cafe/favorites', ['product_id' => $this->coffee->id], $h)->assertCreated()->assertJsonPath('data.favorites_count', 2);

        $this->getJson('/api/v1/cafe/favorites', $h)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'قهوة')
            ->assertJsonPath('data.0.min_price', '40.00')
            ->assertJsonPath('data.0.is_favorite', true)
            ->assertJsonStructure(['data' => [['id', 'name', 'image_url', 'min_price', 'category_id', 'default_variant_id', 'is_favorite', 'favorited_at']]]);
        $this->getJson('/api/v1/cafe/favorites/ids', $h)->assertOk()->assertJsonCount(2, 'data');

        $this->deleteJson("/api/v1/cafe/favorites/{$this->coffee->id}", [], $h)->assertOk()->assertJsonPath('is_favorite', false)->assertJsonPath('favorites_count', 1);
        $this->deleteJson("/api/v1/cafe/favorites/{$this->coffee->id}", [], $h)->assertOk();
        $this->getJson('/api/v1/cafe/favorites', $h)->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'شاي');
    }

    public function test_favorites_are_per_customer_and_flag_product_cards(): void
    {
        $this->postJson('/api/v1/cafe/favorites', ['product_id' => $this->coffee->id], $this->as($this->customer))->assertCreated();
        FeaturedSection::create(['title' => 'مختارات'])->syncProducts([$this->coffee->id, $this->tea->id]);

        $h = $this->as($this->customer);
        $cards = collect($this->getJson('/api/v1/cafe/products', $h)->assertOk()->json('data'))->keyBy('id');
        $this->assertTrue($cards[$this->coffee->id]['is_favorite']);
        $this->assertFalse($cards[$this->tea->id]['is_favorite']);
        $this->getJson("/api/v1/cafe/products/{$this->coffee->id}", $h)->assertJsonPath('is_favorite', true);
        $this->getJson('/api/v1/cafe/featured-sections', $h)->assertJsonPath('data.0.products.0.is_favorite', true)->assertJsonPath('data.0.products.1.is_favorite', false);
        $this->getJson('/api/v1/cafe/products?search=' . urlencode('قهوة'), $h)->assertJsonPath('data.0.is_favorite', true);

        $this->getJson('/api/v1/cafe/favorites', $this->as($this->other))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/cafe/products/{$this->coffee->id}", $this->as($this->other))->assertJsonPath('is_favorite', false);
    }

    public function test_inactive_products_cannot_be_added_and_disappear_from_the_list(): void
    {
        $h = $this->as($this->customer);
        $this->postJson('/api/v1/cafe/favorites', ['product_id' => $this->coffee->id], $h)->assertCreated();

        $this->coffee->update(['is_active' => false]);
        $this->getJson('/api/v1/cafe/favorites', $h)->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/cafe/favorites', ['product_id' => $this->coffee->id], $h)->assertUnprocessable();
        $this->postJson('/api/v1/cafe/favorites', ['product_id' => 9999], $h)->assertUnprocessable();

        $this->coffee->update(['is_active' => true]);
        $this->getJson('/api/v1/cafe/favorites', $h)->assertJsonCount(1, 'data');
    }
}
