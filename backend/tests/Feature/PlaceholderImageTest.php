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

    public function test_promo_without_image_uses_the_default_picture(): void
    {
        $promo = Promo::create(['description' => 'عرض', 'is_active' => true]);

        $this->assertSame(Placeholder::url('promo'), $promo->image_url);
    }
}
