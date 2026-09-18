<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Profit needs the cost the item had when it was sold, the same way the order
// already keeps the price it was sold at. Rows that predate this get today's
// cost, which is the best figure that still exists for them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 2)->nullable()->after('unit_price');
        });

        DB::table('order_items')->whereNull('unit_cost')->orderBy('id')->chunkById(500, function ($items) {
            $costs = DB::table('product_variants')->whereIn('id', $items->pluck('product_variant_id')->unique())->pluck('cost_price', 'id');
            foreach ($items as $item) {
                if (($cost = $costs[$item->product_variant_id] ?? null) !== null) {
                    DB::table('order_items')->where('id', $item->id)->update(['unit_cost' => $cost]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
