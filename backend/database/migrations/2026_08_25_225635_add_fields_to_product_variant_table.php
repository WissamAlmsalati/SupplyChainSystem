<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variant', function (Blueprint $table) {
            $table->string('sku', 50)->nullable()->change();
            $table->string('barcode', 100)->nullable()->after('sku');
            $table->decimal('sell_price', 10, 2)->nullable()->after('price');
            $table->decimal('cost_price', 10, 2)->nullable()->after('sell_price');
            $table->integer('stock_quantity')->unsigned()->nullable()->after('cost_price');
            $table->string('status', 50)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('product_variant', function (Blueprint $table) {
            $table->string('sku', 50)->nullable(false)->change();
            $table->dropColumn(['barcode', 'sell_price', 'cost_price', 'stock_quantity', 'status']);
        });
    }
};
