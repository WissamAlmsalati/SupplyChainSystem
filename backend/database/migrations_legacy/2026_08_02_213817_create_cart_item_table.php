<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_item', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cart_id')->unsigned();
            $table->integer('product_variant_id')->unsigned();
            $table->integer('quantity');
            $table->decimal('price_at_add', 10, 2);

            $table->foreign('cart_id')->references('id')->on('cart')->onDelete('cascade');
            $table->foreign('product_variant_id')->references('id')->on('product_variant');

            $table->index('cart_id', 'idx_cart_item_cart');
        });

        // ponytail: MySQL-only syntax; quantity validation lives in the FormRequests.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cart_item ADD CONSTRAINT chk_cart_item_quantity_positive CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_item');
    }
};
