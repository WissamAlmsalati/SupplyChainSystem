<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('supplier_id')->unsigned();
            $table->integer('warehouse_id')->unsigned();
            $table->timestamp('order_date')->default(now());
            $table->string('status', 20)->default('pending');

            $table->foreign('supplier_id')->references('id')->on('supplier');
            $table->foreign('warehouse_id')->references('id')->on('warehouse');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order');
    }
};
