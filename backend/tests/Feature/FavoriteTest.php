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

        $this->postJson('/api/v1/customer/favorites', ['product_id' => $this->tea->id], $h)->assertCreated()->assertJsonPath('data.favorites_count', 1);
        $this->travel(1)->seconds();
        $this->postJson('/api/v1/customer/favorites', ['product_id' => $this->coffee->id], $h)->assertCreated();
        $this->postJson('/api/v1/customer/favorites', ['product_id' => $this->coffee->id], $h)->assertCreated()->assertJsonPath('data.favorites_count', 2);

        $this->getJson('/api/v1/customer/favorites', $h)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'قهوة')
            ->assertJsonPath('data.0.min_price', '40.00')
            ->assertJsonPath('data.0.is_favorite', true)
            ->assertJsonStructure(['data' => [['id', 'name', 'image_url', 'min_price', 'category_id', 'default_variant_id', 'is_favorite', 'favorited_at']]]);
        $this->getJson('/api/v1/customer/favorites/ids', $h)->assertOk()->assertJsonCount(2, 'data');

        $this->deleteJson("/api/v1/customer/favorites/{$this->coffee->id}", [], $h)->assertOk()->assertJsonPath('is_favorite', false)->assertJsonPath('favorites_count', 1);
        $this->deleteJson("/api/v1/customer/favorites/{$this->coffee->id}", [], $h)->assertOk();
        $this->getJson('/api/v1/customer/favorites', $h)->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'شاي');
    }

    public function test_favorites_are_per_customer_and_flag_product_cards(): void
    {
        $this->postJson('/api/v1/customer/favorites', ['product_id' => $this->coffee->id], $this->as($this->customer))->assertCreated();
        FeaturedSection::create(['title' => 'مختارات'])->syncProducts([$this->coffee->id, $this->tea->id]);

        $h = $this->as($this->customer);
        $cards = collect($this->getJson('/api/v1/customer/products', $h)->assertOk()->json('data'))->keyBy('id');
        $this->assertTrue($cards[$this->coffee->id]['is_favorite']);
        $this->assertFalse($cards[$this->tea->id]['is_favorite']);
        $this->getJson("/api/v1/customer/products/{$this->coffee->id}", $h)->assertJsonPath('is_favorite', true);
        $this->getJson('/api/v1/customer/featured-sections', $h)->assertJsonPath('data.0.products.0.is_favorite', true)->assertJsonPath('data.0.products.1.is_favorite', false);
        $this->getJson('/api/v1/customer/products?search=' . urlencode('قهوة'), $h)->assertJsonPath('data.0.is_favorite', true);

        $this->getJson('/api/v1/customer/favorites', $this->as($this->other))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/customer/products/{$this->coffee->id}", $this->as($this->other))->assertJsonPath('is_favorite', false);
    }

    public function test_inactive_products_cannot_be_added_and_disappear_from_the_list(): void
    {
        $h = $this->as($this->customer);
        $this->postJson('/api/v1/customer/favorites', ['product_id' => $this->coffee->id], $h)->assertCreated();

        $this->coffee->update(['is_active' => false]);
        $this->getJson('/api/v1/customer/favorites', $h)->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/customer/favorites', ['product_id' => $this->coffee->id], $h)->assertUnprocessable();
        $this->postJson('/api/v1/customer/favorites', ['product_id' => 9999], $h)->assertUnprocessable();

        $this->coffee->update(['is_active' => true]);
        $this->getJson('/api/v1/customer/favorites', $h)->assertJsonCount(1, 'data');
    }

    public function test_favorites_list_is_paginated_newest_first(): void
    {
        $h = $this->as($this->customer);
        $this->postJson('/api/v1/customer/favorites', ['product_id' => $this->tea->id], $h)->assertCreated();
        $this->travel(1)->seconds();
        $this->postJson('/api/v1/customer/favorites', ['product_id' => $this->coffee->id], $h)->assertCreated();

        $this->getJson('/api/v1/customer/favorites?per_page=1', $h)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'قهوة')
            ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/v1/customer/favorites?per_page=1&page=2', $h)->assertJsonPath('data.0.name', 'شاي')->assertJsonPath('meta.current_page', 2);
    }

    public function test_cart_and_recurring_cart_products_carry_the_heart_flag(): void
    {
        $h = $this->as($this->customer);
        $this->postJson('/api/v1/customer/favorites', ['product_id' => $this->coffee->id], $h)->assertCreated();

        $coffeeVariant = $this->coffee->variants()->first()->id;
        $teaVariant = $this->tea->variants()->first()->id;

        $this->postJson('/api/v1/customer/cart/items', ['product_variant_id' => $coffeeVariant, 'quantity' => 1], $h)->assertCreated()
            ->assertJsonPath('data.data.product_variant.product.is_favorite', true)
            ->assertJsonPath('data.cart.items.0.product_variant.product.is_favorite', true);
        $this->postJson('/api/v1/customer/cart/items', ['product_variant_id' => $teaVariant, 'quantity' => 1], $h)->assertCreated();

        $items = collect($this->getJson('/api/v1/customer/cart', $h)->assertOk()->json('data.items'))->keyBy('product_variant_id');
        $this->assertTrue($items[$coffeeVariant]['product_variant']['product']['is_favorite']);
        $this->assertFalse($items[$teaVariant]['product_variant']['product']['is_favorite']);

        $rc = $this->postJson('/api/v1/customer/recurring-carts', ['name' => 'أسبوعية', 'items' => [
            ['product_variant_id' => $coffeeVariant, 'quantity' => 1], ['product_variant_id' => $teaVariant, 'quantity' => 2],
        ]], $h)->assertCreated()->json('data');
        $flags = collect($rc['items'])->mapWithKeys(fn ($i) => [$i['product_variant_id'] => $i['product_variant']['product']['is_favorite']]);
        $this->assertSame([$coffeeVariant => true, $teaVariant => false], $flags->all());

        $this->getJson("/api/v1/customer/recurring-carts/{$rc['id']}", $h)->assertJsonPath('items.0.product_variant.product.is_favorite', true);
        $this->getJson('/api/v1/customer/recurring-carts', $h)->assertJsonPath('data.0.items.0.product_variant.product.is_favorite', true);

        // Removing the favorite turns the heart off everywhere.
        $this->deleteJson("/api/v1/customer/favorites/{$this->coffee->id}", [], $h)->assertOk();
        $this->getJson('/api/v1/customer/cart', $h)->assertJsonPath('data.items.0.product_variant.product.is_favorite', false);
        $this->getJson("/api/v1/customer/products/{$this->coffee->id}", $h)->assertJsonPath('is_favorite', false);
    }
}
