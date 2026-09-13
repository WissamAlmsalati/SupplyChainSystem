<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // type 'shopping' = the one working cart (emptied on checkout);
        // type 'recurring' = named carts the customer re-orders from.
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('shopping');
            $table->string('name', 100)->nullable();
            // Equals user_id only for shopping carts, so the unique index allows
            // one shopping cart per user and any number of recurring carts.
            $table->unsignedBigInteger('shopping_owner_id')->nullable()
                ->virtualAs("case when type = 'shopping' then user_id end");
            $table->timestamps();

            $table->unique('shopping_owner_id');
            $table->index(['user_id', 'type']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['cart_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
