<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Goods that come back after delivery. A return never edits the order it
// belongs to: the order stays what was sold, and what the customer owes is the
// order total minus the value of its returns.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('reason', 255);
            // Value of the returned goods at the price they were sold for.
            $table->decimal('total_value', 10, 2);
            // What actually went back to the customer; less than total_value
            // when the order had not been paid in full.
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->string('refund_method', 20)->default('none');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            // restock = sellable again, damaged = written off
            $table->string('condition', 20);
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
        Schema::dropIfExists('order_returns');
    }
};
