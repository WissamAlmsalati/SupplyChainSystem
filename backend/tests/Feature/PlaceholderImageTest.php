<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Promo;
use App\Support\Placeholder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceholderImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_placeholder_endpoint_serves_svg_without_auth(): void
    {
        foreach (Placeholder::KINDS as $kind) {
            $res = $this->get("/api/v1/placeholder/{$kind}.svg");
            $res->assertOk()->assertHeader('content-type', 'image/svg+xml');
            $this->assertStringContainsString('<svg', $res->getContent());
        }

        // Unknown kinds fall back to the product artwork instead of failing.
        $this->get('/api/v1/placeholder/whatever.svg')->assertOk();
    }

    public function test_product_without_image_uses_the_default_picture(): void
    {
        $category = Category::create(['name' => 'قهوة']);
        $product = Product::create(['name' => 'بن', 'category_id' => $category->id, 'is_active' => true]);

        $this->assertSame(Placeholder::url('product'), $product->image_url);

        ProductImage::create(['product_id' => $product->id, 'path' => 'products/real.jpg', 'is_primary' => true]);
        $product->unsetRelation('allImages');

        $this->assertSame('/storage/products/real.jpg', $product->fresh()->image_url);
    }

    public function test_product_response_says_whether_the_picture_is_real(): void
    {
        $category = Category::create(['name' => 'قهوة']);
        $product = Product::create(['name' => 'بن', 'category_id' => $category->id, 'is_active' => true]);

        $this->assertSame('placeholder', $product->image_type);

        ProductImage::create(['product_id' => $product->id, 'path' => 'products/real.jpg', 'is_primary' => true]);

        $this->assertSame('uploaded', $product->fresh()->image_type);
    }

    public function test_category_carries_a_picture_with_a_default(): void
    {
        $category = Category::create(['name' => 'قهوة']);

        $this->assertSame(Placeholder::url('category'), $category->image_url);
        $this->assertSame('placeholder', $category->image_type);

        $category->update(['image' => 'categories/real.jpg']);

        $this->assertSame('/storage/categories/real.jpg', $category->image_url);
        $this->assertSame('uploaded', $category->image_type);
    }

    public function test_promo_without_image_uses_the_default_picture(): void
    {
        $promo = Promo::create(['description' => 'عرض', 'is_active' => true]);

        $this->assertSame(Placeholder::url('promo'), $promo->image_url);
    }
}
