<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('delegate_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->string('source', 20)->default('app');

            // Delivery address copied at order time; address_id is only a link
            // back and may be cleared, the snapshot below stays unchanged.
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_address_name', 100)->nullable();
            $table->string('delivery_city', 100)->nullable();
            $table->string('delivery_street', 200)->nullable();
            $table->json('delivery_phones')->nullable();
            $table->decimal('delivery_latitude', 10, 8)->nullable();
            $table->decimal('delivery_longitude', 11, 8)->nullable();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->timestamp('placed_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['user_id', 'placed_at']);
            $table->index(['delegate_id', 'status']);
        });

        // Product and size names are copied so history survives catalog edits.
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('product_name', 150);
            $table->string('variant_name', 100)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
        });

        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
