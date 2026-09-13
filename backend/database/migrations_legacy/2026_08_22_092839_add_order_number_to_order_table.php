<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->string('order_number', 50)->unique()->nullable()->after('id');
        });

        // Backfill existing orders with generated order numbers
        Order::query()->whereNull('order_number')->orderBy('id')->each(function (Order $order) {
            $prefix = 'ORD-' . date('Y', strtotime($order->order_date ?? now())) . '-';
            $order->update(['order_number' => $prefix . str_pad($order->id, 5, '0', STR_PAD_LEFT)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->dropColumn('order_number');
        });
    }
};
