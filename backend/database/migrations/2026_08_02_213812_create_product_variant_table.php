<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_id')->unsigned();
            $table->string('sku', 50)->unique();
            $table->string('attribute_name', 50)->nullable();
            $table->string('attribute_value', 50)->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);

            $table->foreign('product_id')->references('id')->on('product')->onDelete('cascade');
            $table->index('product_id', 'idx_product_variant_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant');
    }
};
