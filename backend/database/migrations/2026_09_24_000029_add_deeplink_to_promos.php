<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A banner points either out of the platform or into it. `link` used to carry
// both, as a web path the dashboard built and every client took apart again
// with a regex — and "/products/123" tells a Flutter app nothing it can route
// on. Now `link` is an external URL only, and a destination inside the apps is
// named: deeplink_entity, plus the id it needs.
return new class extends Migration
{
    // What the old paths meant, read once here rather than for ever by every client.
    private const PATHS = ['/' => 'home', '/products' => 'products', '/orders' => 'orders', '/cart' => 'cart', '/profile' => 'profile'];

    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->string('deeplink_entity', 40)->nullable()->after('link');
            $table->unsignedBigInteger('deeplink_entity_id')->nullable()->after('deeplink_entity');
        });

        foreach (DB::table('promos')->whereNotNull('link')->get(['id', 'link']) as $promo) {
            $link = trim((string) $promo->link);

            // Already outside the platform: it stays in link, untouched.
            if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
                continue;
            }

            if (preg_match('#^/products/(\d+)$#', $link, $m)) {
                [$entity, $entityId] = ['product', (int) $m[1]];
            } elseif (isset(self::PATHS[$link])) {
                [$entity, $entityId] = [self::PATHS[$link], null];
            } else {
                // A path nobody planned: leave it alone rather than guess at it.
                continue;
            }

            DB::table('promos')->where('id', $promo->id)
                ->update(['deeplink_entity' => $entity, 'deeplink_entity_id' => $entityId, 'link' => null]);
        }
    }

    public function down(): void
    {
        $flip = array_flip(self::PATHS);

        foreach (DB::table('promos')->whereNotNull('deeplink_entity')->get(['id', 'deeplink_entity', 'deeplink_entity_id']) as $promo) {
            $link = $promo->deeplink_entity === 'product' && $promo->deeplink_entity_id
                ? '/products/'.$promo->deeplink_entity_id
                : ($flip[$promo->deeplink_entity] ?? null);

            if ($link !== null) {
                DB::table('promos')->where('id', $promo->id)->update(['link' => $link]);
            }
        }

        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn(['deeplink_entity', 'deeplink_entity_id']);
        });
    }
};
