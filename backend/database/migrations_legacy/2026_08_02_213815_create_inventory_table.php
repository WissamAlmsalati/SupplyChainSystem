<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('warehouse_id')->unsigned();
            $table->integer('product_variant_id')->unsigned();
            $table->integer('quantity')->default(0);
            $table->timestamp('updated_at')->default(now());

            $table->foreign('warehouse_id')->references('id')->on('warehouse');
            $table->foreign('product_variant_id')->references('id')->on('product_variant');

            $table->unique(['warehouse_id', 'product_variant_id']);
            $table->index('product_variant_id', 'idx_inventory_variant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
