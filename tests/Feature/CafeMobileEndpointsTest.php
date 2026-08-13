<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Cafe;
use App\Models\CafeBranch;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserType;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CafeMobileEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $cafeUser;
    protected Cafe $cafe;
    protected CafeBranch $branch;
    protected ProductVariant $variant;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $cafeType = UserType::create(['name' => 'cafe']);
        UserType::create(['name' => 'admin']);
        UserType::create(['name' => 'super_admin']);
        UserType::create(['name' => 'delegate']);

        $permissions = collect([
            'ORDERS_VIEW', 'ORDERS_EDIT', 'ORDERS_CREATE',
            'CAFE_BRANCHES_VIEW', 'CAFE_BRANCHES_CREATE', 'CAFE_BRANCHES_EDIT', 'CAFE_BRANCHES_DELETE',
            'INVENTORY_VIEW',
        ])->map(fn ($code) => Permission::create(['code' => $code]));
        $cafeType->permissions()->sync($permissions->pluck('id'));

        $this->cafe = Cafe::create([
            'name' => 'مقهى اختبار',
            'contact_info' => '0911111111',
            'is_active' => true,
        ]);

        $this->cafeUser = AppUser::create([
            'name' => 'Cafe Owner',
            'email' => 'cafe@test.com',
            'mobile_number' => '0911111111',
            'password_hash' => bcrypt('password'),
            'user_type_id' => $cafeType->id,
            'cafe_id' => $this->cafe->id,
            'is_active' => true,
        ]);

        $zone = DeliveryZone::create([
            'hex_id' => '842da29ffffffff',
            'name' => 'منطقة اختبار',
            'delivery_price' => 5,
            'latitude' => 27.0,
            'longitude' => 17.0,
            'is_active' => true,
        ]);

        $this->branch = CafeBranch::create([
            'cafe_id' => $this->cafe->id,
            'name' => 'فرع رئيسي',
            'city' => 'طرابلس',
            'street' => 'الشارع الرئيسي',
            'latitude' => 27.0,
            'longitude' => 17.0,
            'delivery_zone_id' => $zone->id,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'مستودع اختبار',
            'city' => 'طرابلس',
            'latitude' => 27.0,
            'longitude' => 17.0,
        ]);

        $category = Category::create(['name' => 'تصنيف اختبار']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'منتج اختبار',
            'description' => 'وصف المنتج',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-001',
            'attribute_value' => 'افتراضي',
            'price' => 10,
            'is_active' => true,
        ]);
    }

    protected function token(): string
    {
        $res = $this->postJson('/api/login', [
            'email' => 'cafe@test.com',
            'password' => 'password',
        ]);

        $res->assertOk();
        $this->assertArrayHasKey('token', $res->json());
        $this->assertArrayHasKey('permissions', $res->json());

        return $res->json('token');;
    }

    public function test_cafe_login_returns_token_and_permissions(): void
    {
        $this->token();
    }

    public function test_cafe_me_returns_user(): void
    {
        $token = $this->token();
        $this->getJson('/api/me', ['Authorization' => "Bearer $token"])
            ->assertOk()
            ->assertJsonPath('email', 'cafe@test.com');
    }

    public function test_cafe_profile(): void
    {
        $token = $this->token();
        $this->getJson('/api/cafe/profile', ['Authorization' => "Bearer $token"])
            ->assertOk()
            ->assertJsonPath('cafe.name', 'مقهى اختبار');
    }

    public function test_cafe_branches_list(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/cafe/branches', ['Authorization' => "Bearer $token"]);
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_cafe_branch_create(): void
    {
        $token = $this->token();
        $res = $this->postJson('/api/cafe/branches', [
            'name' => 'فرع جديد',
            'city' => 'بنغازي',
            'street' => 'شارع جمال',
            'latitude' => 27.1,
            'longitude' => 17.1,
            'is_active' => true,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated();
        $this->assertDatabaseHas('cafe_branch', ['name' => 'فرع جديد']);
    }

    public function test_cafe_branch_orders(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/cafe/branches/' . $this->branch->id . '/orders', ['Authorization' => "Bearer $token"]);
        $res->assertOk();
        $this->assertIsArray($res->json('data'));
    }

    public function test_cafe_orders_list(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/cafe/orders', ['Authorization' => "Bearer $token"]);
        $res->assertOk();
        $this->assertIsArray($res->json('data'));
    }

    public function test_cafe_order_create(): void
    {
        $token = $this->token();
        $res = $this->postJson('/api/cafe/orders', [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 3,
                    'unit_price' => 10,
                ],
            ],
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated();
        $this->assertDatabaseHas('order', [
            'branch_id' => $this->branch->id,
            'user_id' => $this->cafeUser->id,
            'status' => 'pending',
        ]);
    }

    public function test_cafe_categories_list(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/cafe/categories', ['Authorization' => "Bearer $token"]);
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_cafe_products_list(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/cafe/products', ['Authorization' => "Bearer $token"]);
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_cafe_product_variants(): void
    {
        $token = $this->token();
        $res = $this->getJson('/api/cafe/products/' . $this->variant->product_id . '/variants', ['Authorization' => "Bearer $token"]);
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_cafe_inventory_list(): void
    {
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 100,
        ]);

        $token = $this->token();
        $res = $this->getJson('/api/inventory', ['Authorization' => "Bearer $token"]);
        $res->assertOk();
    }
}
