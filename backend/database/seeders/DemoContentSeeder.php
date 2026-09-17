<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AppUser;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Promo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Support\Placeholder;
use Illuminate\Support\Facades\Storage;

// Demo content the other seeders leave empty: product images, promo banners,
// favorites and notifications. Images are generated as SVG files on the public
// disk so the apps show something without any external download.
class DemoContentSeeder extends Seeder
{
    private array $palette = [
        ['#0f766e', '#ccfbf1'],
        ['#b45309', '#fef3c7'],
        ['#1d4ed8', '#dbeafe'],
        ['#9333ea', '#f3e8ff'],
        ['#be123c', '#ffe4e6'],
        ['#15803d', '#dcfce7'],
    ];

    public function run(): void
    {
        $this->categoryImages();
        $this->productImages();
        $this->promos();
        $this->favorites();
        $this->notifications();
    }

    /**
     * A picture for the demo data: the shared placeholder artwork, tinted and
     * captioned so each product and banner looks different.
     */
    private function card(string $title, string $subtitle, int $index, string $kind = 'product'): string
    {
        [$ink, $bg] = $this->palette[$index % count($this->palette)];
        $title = htmlspecialchars($title, ENT_XML1);
        $subtitle = htmlspecialchars($subtitle, ENT_XML1);
        $svg = Placeholder::svg($kind);
        $width = $kind === 'promo' ? 1200 : 600;

        // Tint the shared artwork, then caption it.
        $svg = str_replace(['#f1f5f9', '#0f766e', '#ccfbf1'], [$bg, $ink, '#ffffff'], $svg);
        $caption = <<<CAPTION
          <text x="50%" y="86%" text-anchor="middle" font-family="system-ui, sans-serif" font-size="42" font-weight="bold" fill="{$ink}">{$title}</text>
          <text x="50%" y="93%" text-anchor="middle" font-family="system-ui, sans-serif" font-size="26" fill="{$ink}" opacity="0.75">{$subtitle}</text>
        </svg>
        CAPTION;

        return str_replace('</svg>', $caption, $svg);
    }

    private function categoryImages(): void
    {
        foreach (Category::all()->values() as $i => $category) {
            $path = "categories/category-{$category->id}.svg";
            Storage::disk('public')->put($path, $this->card($category->name, 'تصنيف', $i, 'category'));

            $category->update(['image' => $path]);
        }
    }

    private function productImages(): void
    {
        foreach (Product::with('variants')->get()->values() as $i => $product) {
            $path = "products/product-{$product->id}.svg";
            Storage::disk('public')->put($path, $this->card($product->name, $product->brand ?? '', $i));

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            // One extra image on the first size, so variant galleries have content.
            if ($variant = $product->variants->first()) {
                $variantPath = "products/product-{$product->id}-variant-{$variant->id}.svg";
                Storage::disk('public')->put($variantPath, $this->card($product->name, $variant->name, $i + 3));

                ProductImage::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'path' => $variantPath,
                    'is_primary' => false,
                    'sort_order' => 1,
                ]);
            }
        }
    }

    private function promos(): void
    {
        $banners = [
            ['خصم 15% على البن', 'على كل أنواع البن حتى نهاية الشهر', '/products?category=قهوة'],
            ['توصيل مجاني', 'للطلبات فوق 500 دينار داخل طرابلس', '/products'],
            ['مستلزمات التحضير', 'فلاتر وأكواب وأدوات بأسعار الجملة', '/products'],
        ];

        foreach ($banners as $i => [$title, $description, $link]) {
            $path = "promos/promo-{$i}.svg";
            Storage::disk('public')->put($path, $this->card($title, $description, $i + 1, 'promo'));

            Promo::create([
                'image' => $path,
                'description' => $description,
                'link' => $link,
                'show_description' => true,
                'is_active' => true,
            ]);
        }
    }

    private function favorites(): void
    {
        $productIds = Product::where('is_active', true)->pluck('id');
        $rows = [];

        foreach ($this->customers() as $i => $customer) {
            foreach ($productIds->shuffle()->take(3 + ($i % 3)) as $productId) {
                $rows[] = ['user_id' => $customer->id, 'product_id' => $productId, 'created_at' => now()];
            }
        }

        DB::table('favorites')->insertOrIgnore($rows);
    }

    private function notifications(): void
    {
        foreach ($this->customers() as $customer) {
            $orders = Order::where('user_id', $customer->id)->latest('id')->take(3)->get();

            foreach ($orders as $i => $order) {
                Notification::create([
                    'user_id' => $customer->id,
                    'type' => 'order',
                    'title' => 'تحديث حالة الطلب ' . $order->order_number,
                    'message' => 'حالة طلبك الآن: ' . $order->status->label(),
                    'link' => '/orders/' . $order->id,
                    'read_at' => $i === 0 ? null : now()->subDays($i),
                    'created_at' => $order->updated_at,
                    'updated_at' => $order->updated_at,
                ]);
            }

            Notification::create([
                'user_id' => $customer->id,
                'type' => 'wallet',
                'title' => 'تم شحن المحفظة',
                'message' => 'تمت الموافقة على طلب الشحن وإضافة الرصيد إلى محفظتك.',
                'link' => '/wallet',
                'read_at' => null,
            ]);
        }

        $admins = AppUser::whereHas('userType', fn ($q) => $q->whereIn('name', [UserRole::Admin->value, UserRole::SuperAdmin->value]))->get();

        foreach ($admins as $admin) {
            foreach (Order::latest('id')->take(2)->get() as $order) {
                Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'order',
                    'title' => 'طلب جديد ' . $order->order_number,
                    'message' => 'تم استلام طلب جديد بقيمة ' . $order->total_amount . ' د.ل',
                    'link' => '/orders/' . $order->id,
                    'read_at' => null,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->created_at,
                ]);
            }
        }
    }

    private function customers()
    {
        return AppUser::whereHas('userType', fn ($q) => $q->where('name', UserRole::Customer->value))->get();
    }
}
