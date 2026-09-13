<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Current on-hand balance per warehouse and variant.
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_variant_id']);
        });

        // Append-only ledger explaining every change to inventories.quantity.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->integer('quantity_change');
            $table->string('type', 20);
            $table->nullableMorphs('reference');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_variant_id', 'created_at']);
        });

        // Stock entering a warehouse; the system is the only source of goods.
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 30)->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 10, 2);
            $table->smallInteger('manufacturing_year')->unsigned()->nullable();
            $table->date('expiry_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventories');
    }
};
