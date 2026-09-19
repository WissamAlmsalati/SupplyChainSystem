<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private AppUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = AppUser::factory()->admin()->create(['name' => 'مدير النظام']);
    }

    private function search(string $q, ?AppUser $as = null, array $extra = [])
    {
        $this->app['auth']->forgetGuards();
        $token = ($as ?? $this->admin)->createToken('t')->plainTextToken;

        return $this->getJson('/api/v1/search?'.http_build_query(['q' => $q] + $extra), ['Authorization' => 'Bearer '.$token]);
    }

    private function group(array $json, string $key): ?array
    {
        return collect($json['groups'])->firstWhere('key', $key);
    }

    public function test_it_finds_rows_of_many_kinds_with_tolerant_arabic(): void
    {
        $customer = AppUser::factory()->customer()->create(['name' => 'مقهى أحمد', 'mobile_number' => '0912345678']);
        AppUser::factory()->delegate()->create(['name' => 'أحمد المندوب']);
        $order = Order::factory()->create(['user_id' => $customer->id, 'delivery_city' => 'مصراتة']);
        $product = Product::create(['category_id' => Category::create(['name' => 'قهوة'])->id, 'name' => 'بن عربي محمص']);
        ProductVariant::create(['product_id' => $product->id, 'name' => '500 جم', 'sku' => 'PRD-0001-03', 'price' => 40]);

        // "احمد" without the hamza finds "أحمد" in three different groups.
        $json = $this->search('احمد')->assertOk()->json();
        $this->assertSame('مقهى أحمد', $this->group($json, 'customers')['items'][0]['title']);
        $this->assertSame('أحمد المندوب', $this->group($json, 'delegates')['items'][0]['title']);
        $this->assertSame("/orders/{$order->id}", $this->group($json, 'orders')['items'][0]['url']);

        // Arabic-Indic digits find a phone number; an SKU finds its variant.
        $this->assertNotNull($this->group($this->search('٠٩١٢٣٤')->json(), 'customers'));
        $this->assertSame('/product-variants/1', $this->group($this->search('prd-0001')->json(), 'variants')['items'][0]['url']);
        // "قهوه" for "قهوة": the category, and the product through its category.
        $json = $this->search('قهوه')->json();
        $this->assertNotNull($this->group($json, 'categories'));
        $this->assertNotNull($this->group($json, 'products'));
    }

    public function test_every_word_must_match_but_each_may_match_a_different_field(): void
    {
        $ahmed = AppUser::factory()->customer()->create(['name' => 'مقهى أحمد']);
        $tripoli = Order::factory()->create(['user_id' => $ahmed->id, 'delivery_city' => 'طرابلس']);
        Order::factory()->create(['user_id' => $ahmed->id, 'delivery_city' => 'بنغازي']);
        Order::factory()->create(['delivery_city' => 'طرابلس']);

        $orders = $this->group($this->search('احمد طرابلس')->assertOk()->json(), 'orders');

        $this->assertCount(1, $orders['items']);
        $this->assertSame($tripoli->id, $orders['items'][0]['id']);
    }

    public function test_a_number_finds_the_row_with_that_id_and_prefix_matches_come_first(): void
    {
        $order = Order::factory()->create();
        $this->assertContains($order->id, array_column($this->group($this->search('#'.$order->id)->json(), 'orders')['items'], 'id'));

        AppUser::factory()->customer()->create(['name' => 'مقهى سالم الجديد']);
        AppUser::factory()->customer()->create(['name' => 'سالم كافيه']);
        $this->assertSame('سالم كافيه', $this->group($this->search('سالم')->json(), 'customers')['items'][0]['title']);
    }

    public function test_a_scope_returns_more_of_one_group_and_has_more_says_when_to_ask(): void
    {
        AppUser::factory()->customer()->count(7)->create(['name' => 'مقهى النخيل']);

        $all = $this->search('النخيل')->json();
        $this->assertCount(5, $this->group($all, 'customers')['items']);
        $this->assertTrue($this->group($all, 'customers')['has_more']);
        $this->assertContains('customers', array_column($all['scopes'], 'key'));

        $scoped = $this->search('النخيل', null, ['only' => 'customers'])->json();
        $this->assertCount(1, $scoped['groups']);
        $this->assertCount(7, $scoped['groups'][0]['items']);
        $this->assertFalse($scoped['groups'][0]['has_more']);

        $this->search('النخيل', null, ['only' => 'nonsense'])->assertUnprocessable();
    }

    public function test_a_group_needs_its_modules_view_code(): void
    {
        AppUser::factory()->customer()->create(['name' => 'مقهى الواحة']);
        Order::factory()->create(['delivery_address_name' => 'فرع الواحة']);

        $role = UserType::firstOrCreate(['name' => 'orders_clerk']);
        $role->permissions()->sync(Permission::where('code', 'ORDERS_VIEW')->pluck('id'));
        $clerk = AppUser::factory()->create(['user_type_id' => $role->id]);

        $json = $this->search('الواحة', $clerk)->assertOk()->json();

        $this->assertNotNull($this->group($json, 'orders'));
        $this->assertNull($this->group($json, 'customers'));
        $this->assertNotContains('customers', array_column($json['scopes'], 'key'));
        $this->search('الواحة', $clerk, ['only' => 'customers'])->assertUnprocessable();
    }

    public function test_notifications_are_only_ever_your_own(): void
    {
        $other = AppUser::factory()->admin()->create();
        Notification::sendTo([$this->admin->id], 'تنبيه مخزون القرفة');
        Notification::sendTo([$other->id], 'تنبيه مخزون القرفة لغيرك');

        $items = $this->group($this->search('القرفة')->json(), 'notifications')['items'];

        $this->assertCount(1, $items);
        $this->assertSame('تنبيه مخزون القرفة', $items[0]['title']);
    }

    public function test_the_apps_are_refused_and_a_short_query_finds_nothing(): void
    {
        $this->search('احمد', AppUser::factory()->customer()->create())->assertForbidden();
        $this->search('احمد', AppUser::factory()->delegate()->create())->assertForbidden();

        $this->search('ا')->assertOk()->assertJsonPath('total', 0);
        // Half of a two-byte character: answered, not a 500.
        $this->search("احمد\xD8")->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/search?q=test')->assertUnauthorized();
    }
}
