<?php

namespace App\Console\Commands;

use App\Models\AppUser;
use App\Models\Cafe;
use App\Models\CafeBranch;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserType;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CafeEndpointsDemo extends Command
{
    protected $signature = 'cafe:endpoints-demo {--output=docs/cafe-endpoints-demo.md}';
    protected $description = 'Run cafe mobile endpoints and write request/response examples to a markdown file';

    private string $baseUrl;
    private string $token = '';
    private int $step = 0;
    private string $markdown = '';

    public function handle(): int
    {
        $this->baseUrl = 'http://localhost/api/v1';
        $this->md("# Cafe Mobile API Endpoints Demo\n\nBase URL: `http://localhost/api`\n");

        $this->setupData();
        $this->login();

        $this->get('/me', 'Get authenticated user');
        $this->get('/cafe/profile', 'Get cafe profile');
        $this->get('/cafe/branches', 'List cafe branches');

        $branch = CafeBranch::where('cafe_id', $this->cafeId())->first();
        $this->get('/cafe/branches/' . $branch?->id . '/orders', 'List branch orders');
        $this->post('/cafe/branches', [
            'name' => 'فرع جديد',
            'city' => 'بنغازي',
            'street' => 'شارع جمال',
            'latitude' => 27.1,
            'longitude' => 17.1,
            'is_active' => true,
        ], 'Create cafe branch');

        $this->get('/cafe/orders', 'List cafe orders');

        $variant = ProductVariant::first();
        $this->post('/cafe/orders', [
            'branch_id' => $branch?->id,
            'items' => [
                [
                    'product_variant_id' => $variant?->id,
                    'quantity' => 2,
                    'unit_price' => 10,
                ],
            ],
        ], 'Create cafe order');

        $this->get('/cafe/categories', 'List categories');
        $this->get('/cafe/products', 'List products');
        $this->get('/cafe/products?category_id=' . Category::first()?->id, 'List products by category');
        $this->get('/cafe/products/' . Product::first()?->id . '/variants', 'Get product variants');

        $outputPath = $this->option('output');
        $fullPath = base_path($outputPath);
        file_put_contents($fullPath, $this->markdown);

        $this->newLine();
        $this->info("Saved markdown report to: $outputPath");

        return self::SUCCESS;
    }

    private function setupData(): void
    {
        $this->step('Setup test data');

        $cafeType = UserType::firstOrCreate(['name' => 'cafe']);
        UserType::firstOrCreate(['name' => 'admin']);
        UserType::firstOrCreate(['name' => 'super_admin']);
        UserType::firstOrCreate(['name' => 'delegate']);

        $codes = ['ORDERS_VIEW', 'ORDERS_EDIT', 'ORDERS_CREATE', 'CAFE_BRANCHES_VIEW', 'CAFE_BRANCHES_CREATE', 'CAFE_BRANCHES_EDIT', 'CAFE_BRANCHES_DELETE', 'INVENTORY_VIEW'];
        $perms = collect($codes)->map(fn ($code) => Permission::firstOrCreate(['code' => $code]));
        $cafeType->permissions()->sync($perms->pluck('id'));

        $cafe = Cafe::firstOrCreate(
            ['name' => 'مقهى اختبار'],
            ['contact_info' => '0911111111', 'is_active' => true]
        );

        $existing = AppUser::where('email', 'cafe.demo@example.com')->first();
        if ($existing) {
            Order::where('user_id', $existing->id)->delete();
            $existing->delete();
        }
        AppUser::create([
            'name' => 'Cafe Demo',
            'email' => 'cafe.demo@example.com',
            'mobile_number' => '0911111111',
            'password_hash' => bcrypt('password'),
            'user_type_id' => $cafeType->id,
            'cafe_id' => $cafe->id,
            'is_active' => true,
        ]);

        $zone = DeliveryZone::firstOrCreate(
            ['hex_id' => '842da29ffffffff'],
            [
                'name' => 'منطقة اختبار',
                'delivery_price' => 5,
                'latitude' => 27.0,
                'longitude' => 17.0,
                'is_active' => true,
            ]
        );

        CafeBranch::firstOrCreate(
            ['name' => 'فرع رئيسي', 'cafe_id' => $cafe->id],
            [
                'city' => 'طرابلس',
                'street' => 'الشارع الرئيسي',
                'latitude' => 27.0,
                'longitude' => 17.0,
                'delivery_zone_id' => $zone->id,
                'is_active' => true,
            ]
        );

        Warehouse::firstOrCreate(
            ['name' => 'مستودع اختبار'],
            ['city' => 'طرابلس', 'latitude' => 27.0, 'longitude' => 17.0]
        );

        $category = Category::firstOrCreate(['name' => 'تصنيف اختبار']);

        Product::updateOrCreate(
            ['name' => 'منتج اختبار'],
            [
                'category_id' => $category->id,
                'description' => 'وصف المنتج',
                'is_active' => true,
            ]
        );

        $product = Product::where('name', 'منتج اختبار')->first();
        ProductVariant::firstOrCreate(
            ['product_id' => $product->id, 'sku' => 'DEMO-001'],
            [
                'attribute_value' => 'افتراضي',
                'price' => 10,
                'is_active' => true,
            ]
        );
    }

    private function login(): void
    {
        $this->step('POST /login');
        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($this->baseUrl . '/login', [
            'email' => 'cafe.demo@example.com',
            'password' => 'password',
        ]);

        $this->printRequest('POST', '/login', ['email' => 'cafe.demo@example.com', 'password' => 'password']);
        $this->printResponse($response);

        $this->token = $response->json('token') ?? '';
    }

    private function get(string $path, string $description): void
    {
        $this->step("GET $path — $description");
        $response = Http::withHeaders($this->headers())->get($this->baseUrl . $path);
        $this->printRequest('GET', $path);
        $this->printResponse($response);
    }

    private function post(string $path, array $body, string $description): void
    {
        $this->step("POST $path — $description");
        $response = Http::withHeaders($this->headers())->post($this->baseUrl . $path, $body);
        $this->printRequest('POST', $path, $body);
        $this->printResponse($response);
    }

    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }

    private function step(string $title): void
    {
        $this->step++;
        $this->newLine();
        $this->info("=== Step {$this->step}: $title ===");
        $this->md("\n## Step {$this->step}: $title\n");
    }

    private function printRequest(string $method, string $path, ?array $body = null): void
    {
        $this->line("<fg=cyan>REQUEST:</> $method $path");
        $this->md("**Request:** `$method $path`\n");
        if ($body) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $this->line('<fg=cyan>BODY:</> ' . $json);
            $this->md("\n**Body:**\n\n```json\n$json\n```\n");
        }
    }

    private function printResponse($response): void
    {
        $status = $response->status();
        $color = $status >= 200 && $status < 300 ? 'green' : 'red';
        $json = json_encode($response->json(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $this->line("<fg=$color>RESPONSE ($status):</> $json");
        $this->md("\n**Response:** `$status`\n\n```json\n$json\n```\n");
    }

    private function md(string $content): void
    {
        $this->markdown .= $content;
    }

    private function cafeId(): int
    {
        return AppUser::where('email', 'cafe.demo@example.com')->value('cafe_id') ?? 0;
    }
}
