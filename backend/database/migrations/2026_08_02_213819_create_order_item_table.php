<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id')->unsigned();
            $table->integer('product_variant_id')->unsigned();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);

            $table->foreign('order_id')->references('id')->on('order')->onDelete('cascade');
            $table->foreign('product_variant_id')->references('id')->on('product_variant');
        });

        DB::statement('ALTER TABLE order_item ADD CONSTRAINT chk_order_item_quantity_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item');
    }
};
